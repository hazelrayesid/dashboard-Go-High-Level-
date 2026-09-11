<?php

namespace App\Services;

use Illuminate\Support\Arr;

class GhlCampaignDashboard
{
    private const DashboardRequestTimeout = 2;

    public function __construct(
        private readonly GhlClient $client,
        private readonly GhlCampaignSegments $segments,
        private readonly GhlContactPresenter $presenter,
    ) {}

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null, groups: array<string, array<int, array<string, mixed>>>, totals: array<string, int>}
     */
    public function build(int $sampleLimit = 6): array
    {
        if (! $this->client->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN dan GHL_LOCATION_ID harus diset di environment.',
                'groups' => $this->segments->emptyGroups(),
                'totals' => [
                    'contacts' => 0,
                    'companies_loaded' => 0,
                    'segments' => $this->segments->count(),
                    'failed_segments' => $this->segments->count(),
                ],
            ];
        }

        $state = [
            'groups' => [],
            'successful' => 0,
            'failed' => 0,
            'status' => null,
            'error' => null,
            'contacts' => 0,
            'companies' => 0,
        ];

        foreach ($this->segments->groups() as $groupName => $groupSegments) {
            $state['groups'][$groupName] = [];

            foreach ($groupSegments as $segment) {
                $state = $this->appendSegment($state, $groupName, $segment, $sampleLimit);
            }
        }

        return [
            'ok' => $state['successful'] > 0,
            'status' => $state['status'],
            'data' => null,
            'error' => $state['successful'] > 0 && $state['failed'] > 0
                ? 'Sebagian segment belum berhasil dibaca dari HighLevel.'
                : $state['error'],
            'groups' => $state['groups'],
            'totals' => [
                'contacts' => $state['contacts'],
                'companies_loaded' => $state['companies'],
                'segments' => $this->segments->count(),
                'failed_segments' => $state['failed'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @param  array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}  $segment
     * @return array<string, mixed>
     */
    private function appendSegment(array $state, string $groupName, array $segment, int $sampleLimit): array
    {
        $result = $this->segmentResult($segment, $sampleLimit);
        $companies = $this->presenter->companies($result['data']);

        $state['status'] ??= $result['status'];
        $state[$result['ok'] ? 'successful' : 'failed']++;
        $state['error'] ??= $result['error'];
        $state['contacts'] += (int) Arr::get($result, 'data.total', 0);
        $state['companies'] += count($companies);
        $state['groups'][$groupName][] = [
            'label' => $segment['label'],
            'tag' => $this->segments->tagLabel($segment),
            'tags' => $this->segments->tags($segment),
            'total' => (int) Arr::get($result, 'data.total', 0),
            'companies' => $companies,
            'ok' => $result['ok'],
            'status' => $result['status'],
        ];

        return $state;
    }

    /**
     * @param  array{label: string, tag?: string, tags?: array<int, string>, requires_empty_audit_report_url?: bool}  $segment
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    private function segmentResult(array $segment, int $sampleLimit): array
    {
        $results = collect($this->segments->tags($segment))
            ->map(fn (string $tag): array => $segment['requires_empty_audit_report_url'] ?? false
                ? $this->client->contactsByTagWithEmptyAuditReportUrl($tag, $sampleLimit, self::DashboardRequestTimeout)
                : $this->client->contactsByTag($tag, $sampleLimit, self::DashboardRequestTimeout));

        $successful = $results->filter(fn (array $result): bool => $result['ok']);

        if ($successful->isEmpty()) {
            return $results->first();
        }

        return [
            'ok' => true,
            'status' => Arr::get($successful->first(), 'status'),
            'data' => [
                'contacts' => $successful
                    ->flatMap(fn (array $result): array => Arr::get($result, 'data.contacts', []))
                    ->take($sampleLimit)
                    ->values()
                    ->all(),
                'total' => $successful->sum(fn (array $result): int => (int) Arr::get($result, 'data.total', 0)),
            ],
            'error' => null,
        ];
    }
}
