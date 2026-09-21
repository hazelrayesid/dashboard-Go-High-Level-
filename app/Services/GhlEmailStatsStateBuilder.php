<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class GhlEmailStatsStateBuilder
{
    public function normalizedRange(array $range): array
    {
        $to = $this->parseDate($range['to'] ?? null) ?? now()->toImmutable();
        $from = $this->parseDate($range['from'] ?? null) ?? $to;

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    public function emptyState(array $window): array
    {
        $from = CarbonImmutable::parse($window['from']);
        $to = CarbonImmutable::parse($window['to']);
        $points = [];

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            $points[] = [
                'date' => $date->toDateString(),
                'label' => $date->format('m/d'),
                'sent' => 0,
                'opened' => 0,
                'clicked' => 0,
                'delivered' => 0,
                'unsubscribed' => 0,
                'spam_complaints' => 0,
                'open_rate' => 0,
                'click_rate' => 0,
            ];
        }

        return [
            'available' => true,
            'complete' => true,
            'error' => null,
            'range' => $window,
            'campaigns_count' => 0,
            'stats_count' => 0,
            'campaign_breakdown' => [],
            'open_rate' => 0,
            'max_open_rate' => 12,
            'totals' => [
                'sent' => 0,
                'delivered' => 0,
                'clicked' => 0,
                'bounced' => 0,
                'opened' => 0,
                'unsubscribed' => 0,
                'spam_complaints' => 0,
            ],
            'points' => $points,
        ];
    }

    public function build(array $result, array $empty, array $workflowNames): array
    {
        if (! $result['ok']) {
            return array_merge($empty, [
                'available' => false,
                'error' => $result['error'],
            ]);
        }

        $items = collect($result['items'])
            ->filter(fn (array $item): bool => (bool) Arr::get($item, 'stats.ok'))
            ->values();
        $totals = $this->totals($items, $empty['totals']);
        $points = $this->chartPoints($items, $empty['points']);
        $openRate = $totals['delivered'] > 0 ? round(($totals['opened'] / $totals['delivered']) * 100, 2) : 0;

        return array_merge($empty, [
            'available' => true,
            'complete' => $result['complete'] ?? true,
            'campaigns_count' => collect($result['items'])->pluck('campaign_key')->unique()->count(),
            'stats_count' => $items->count(),
            'totals' => $totals,
            'open_rate' => $openRate,
            'points' => $points,
            'campaign_breakdown' => $this->campaignBreakdown($items, $result['breakdown_placeholders'] ?? [], $workflowNames),
            'max_open_rate' => max(12, (int) ceil(collect($points)->max('open_rate') ?? 0)),
        ]);
    }

    private function totals($items, array $emptyTotals): array
    {
        return $items->reduce(function (array $carry, array $item): array {
            $stats = Arr::get($item, 'stats.stats', []);

            $carry['sent'] += (int) Arr::get($stats, 'sent', 0);
            $carry['delivered'] += (int) Arr::get($stats, 'delivered', 0);
            $carry['opened'] += (int) Arr::get($stats, 'opened', 0);
            $carry['clicked'] += (int) Arr::get($stats, 'clicked', 0);
            $carry['unsubscribed'] += (int) Arr::get($stats, 'unsubscribed', 0);
            $carry['spam_complaints'] += (int) Arr::get($stats, 'complained', 0);
            $carry['bounced'] += (int) Arr::get($stats, 'permanentFail', 0)
                + (int) Arr::get($stats, 'temporaryFail', 0)
                + (int) Arr::get($stats, 'rejected', 0)
                + (int) Arr::get($stats, 'failed', 0);

            return $carry;
        }, $emptyTotals);
    }

    private function campaignBreakdown($items, array $placeholders, array $workflowNames): array
    {
        $breakdown = $items
            ->groupBy('campaign_key')
            ->map(fn ($group) => $this->breakdownRow($group))
            ->values()
            ->all();
        $existing = collect($breakdown)
            ->pluck('campaign_name')
            ->map(fn (string $name): string => strtolower($name))
            ->flip();
        $missing = collect($placeholders)
            ->reject(fn (array $placeholder): bool => $existing->has(strtolower((string) $placeholder['campaign_name'])))
            ->values()
            ->all();

        return collect([...$breakdown, ...$missing])
            ->sortBy(function (array $item) use ($workflowNames): int {
                $position = array_search((string) $item['campaign_name'], $workflowNames, true);

                return $position === false ? 999 : $position;
            })
            ->values()
            ->all();
    }

    private function breakdownRow($items): array
    {
        $first = $items->first();
        $stats = $items->pluck('stats.stats');
        $delivered = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'delivered', 0));
        $opened = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'opened', 0));
        $clicked = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'clicked', 0));
        $softBounced = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'temporaryFail', 0));
        $hardBounced = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'permanentFail', 0));
        $otherBounced = $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'rejected', 0) + (int) Arr::get($stat, 'failed', 0));

        return [
            'campaign_name' => $first['campaign_name'] ?? 'Unknown campaign',
            'email_name' => $first['email_name'] ?? null,
            'source_label' => $first['source_label'] ?? 'Workflow Campaign',
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'soft_bounced' => $softBounced,
            'hard_bounced' => $hardBounced,
            'bounced' => $softBounced + $hardBounced + $otherBounced,
            'unsubscribed' => $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'unsubscribed', 0)),
            'spam_complaints' => $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'complained', 0)),
            'replied' => $stats->sum(fn (array $stat): int => (int) Arr::get($stat, 'replied', 0)),
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_rate' => $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0,
        ];
    }

    private function chartPoints($items, array $fallbackPoints): array
    {
        $pointsByDate = $items
            ->map(fn (array $item): array => $this->pointFromItem($item))
            ->filter(fn (array $point): bool => $point['delivered'] > 0)
            ->groupBy('date')
            ->map(fn ($datePoints, string $date): array => $this->pointFromGroup($datePoints, $date))
            ->sortKeys();

        if ($pointsByDate->isEmpty()) {
            return $fallbackPoints;
        }

        $points = collect($fallbackPoints)
            ->map(fn (array $fallbackPoint): array => $pointsByDate->get($fallbackPoint['date'], $fallbackPoint))
            ->values();

        if ($points->count() <= 10) {
            return $points->all();
        }

        return $points
            ->groupBy(fn (array $point): string => CarbonImmutable::parse($point['date'])->format('Y-m'))
            ->map(function ($monthPoints, string $month): array {
                return $this->pointFromGroup($monthPoints, $month.'-01', 'M Y');
            })
            ->sortKeys()
            ->values()
            ->all();
    }

    private function pointFromItem(array $item): array
    {
        $stats = Arr::get($item, 'stats.stats', []);

        return [
            'date' => $item['date'],
            'sent' => (int) Arr::get($stats, 'sent', 0),
            'opened' => (int) Arr::get($stats, 'opened', 0),
            'clicked' => (int) Arr::get($stats, 'clicked', 0),
            'delivered' => (int) Arr::get($stats, 'delivered', 0),
            'unsubscribed' => (int) Arr::get($stats, 'unsubscribed', 0),
            'spam_complaints' => (int) Arr::get($stats, 'complained', 0),
        ];
    }

    private function pointFromGroup($points, string $date, string $labelFormat = 'm/d'): array
    {
        $delivered = $points->sum('delivered');
        $opened = $points->sum('opened');
        $clicked = $points->sum('clicked');

        return [
            'date' => $date,
            'label' => CarbonImmutable::parse($date)->format($labelFormat),
            'sent' => $points->sum('sent'),
            'opened' => $opened,
            'clicked' => $clicked,
            'delivered' => $delivered,
            'unsubscribed' => $points->sum('unsubscribed'),
            'spam_complaints' => $points->sum('spam_complaints'),
            'open_rate' => $delivered > 0 ? round(($opened / $delivered) * 100, 2) : 0,
            'click_rate' => $delivered > 0 ? round(($clicked / $delivered) * 100, 2) : 0,
        ];
    }

    private function parseDate(?string $date): ?CarbonImmutable
    {
        if (blank($date)) {
            return null;
        }

        try {
            return CarbonImmutable::parse($date)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
