<?php

namespace App\Services;

class CompanyCampaignControlViewData
{
    /**
     * @param  array<string, mixed>  $dashboard
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     * @param  array<string, mixed>  $googleCalendar
     * @return array<string, mixed>
     */
    public function make(array $dashboard, array $dateRange, array $googleCalendar): array
    {
        $groups = $dashboard['groups'];
        $totals = $dashboard['totals'];
        $groupTotals = collect($groups)->map(fn (array $segments): int => collect($segments)->sum('total'));
        $allSegments = collect($groups)
            ->flatMap(fn (array $segments, string $groupName): array => collect($segments)
                ->map(fn (array $segment): array => $segment + ['group' => $groupName])
                ->all());
        $companyQueue = $this->companyQueue($allSegments);

        return [
            'dashboard' => $dashboard,
            'dateRange' => $dateRange,
            'googleCalendar' => $googleCalendar,
            'groups' => $groups,
            'totals' => $totals,
            'hasPartialFailure' => $dashboard['ok'] && $totals['failed_segments'] > 0,
            'maxGroupTotal' => max($groupTotals->max() ?? 1, 1),
            'sentTotal' => ($groupTotals['Sent, has website'] ?? 0) + ($groupTotals['Sent, no website'] ?? 0),
            'reportTotal' => $groupTotals['Report opened'] ?? 0,
            'remainingTotal' => $groupTotals['Remaining'] ?? 0,
            'reportRate' => $this->reportRate($groupTotals),
            'prioritySegments' => $allSegments->sortByDesc('total')->take(5),
            'companyQueue' => $companyQueue,
            'visibleCompanyCount' => $companyQueue->sum(fn (array $segment): int => count($segment['companies'])),
            'filterOptions' => $this->filterOptions(),
            'filterKey' => fn (string $groupName, string $tag): string => $this->filterKey($groupName, $tag),
        ];
    }

    private function reportRate(\Illuminate\Support\Collection $groupTotals): float|int
    {
        $sentTotal = ($groupTotals['Sent, has website'] ?? 0) + ($groupTotals['Sent, no website'] ?? 0);

        return $sentTotal > 0 ? round((($groupTotals['Report opened'] ?? 0) / $sentTotal) * 100, 1) : 0;
    }

    private function companyQueue(\Illuminate\Support\Collection $segments): \Illuminate\Support\Collection
    {
        return $segments
            ->map(fn (array $segment): array => array_merge($segment, [
                'filter_key' => $this->filterKey($segment['group'], $segment['tag']),
                'companies' => collect($segment['companies'])
                    ->map(fn (array $company): array => $company + [
                        'segment' => $segment['label'],
                        'tag' => $segment['tag'],
                        'group' => $segment['group'],
                    ])
                    ->all(),
            ]));
    }

    private function filterKey(string $groupName, string $tag): string
    {
        $keys = [(string) str($groupName)->lower()->replace([',', ' '], ['', '-']), $this->segmentKey($tag)];

        foreach (['has website', 'no website', 'plain', 'styled', 'top4 signup', 'report generated'] as $match) {
            if (str_contains($tag, $match)) {
                $keys[] = str($match)->replace(' ', '-')->toString();
            }
        }

        if (in_array($tag, ['top4 signup', 'crazy domains'], true)) {
            $keys[] = 'remaining';
        }

        return collect($keys)->unique()->implode(' ');
    }

    private function segmentKey(string $tag): string
    {
        return (string) str($tag)->lower()
            ->replace(['audit outreach - ', ' (top4 signup)', ' - ', ' '], ['', ' top4 signup', '-', '-']);
    }

    /**
     * @return array<int, array{label: string, filter: string}>
     */
    private function filterOptions(): array
    {
        return [
            ['label' => 'All', 'filter' => 'all'],
            ['label' => 'Has website', 'filter' => 'has-website'],
            ['label' => 'No website', 'filter' => 'no-website'],
            ['label' => 'Report generated', 'filter' => 'report-generated'],
            ['label' => 'Remaining', 'filter' => 'remaining'],
        ];
    }
}
