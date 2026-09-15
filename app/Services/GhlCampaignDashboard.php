<?php

namespace App\Services;

use Illuminate\Support\Arr;

class GhlCampaignDashboard
{
    public function __construct(
        private readonly GhlCampaignSegments $segments,
        private readonly GhlContactPresenter $presenter,
        private readonly GhlContactRepository $contacts,
    ) {}

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null, groups: array<string, array<int, array<string, mixed>>>, totals: array<string, int>}
     */
    public function build(int $sampleLimit = 6, array $dateRange = []): array
    {
        if (! $this->contacts->hasSyncedContacts()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'No HighLevel contacts are synced yet. Run Sync GHL data to load PostgreSQL.',
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
            'contacts' => 0,
            'companies' => 0,
            'failed' => 0,
        ];

        foreach ($this->segments->groups() as $groupName => $groupSegments) {
            $state['groups'][$groupName] = [];

            foreach ($groupSegments as $segment) {
                $state = $this->appendSegment($state, $groupName, $segment, $sampleLimit, $dateRange);
            }
        }

        return [
            'ok' => true,
            'status' => null,
            'data' => null,
            'error' => null,
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
    private function appendSegment(array $state, string $groupName, array $segment, int $sampleLimit, array $dateRange): array
    {
        $result = $this->contacts->segmentResult($segment, $this->segments->tags($segment), $sampleLimit, $dateRange);
        $companies = $this->presenter->companies($result['data']);

        $state['contacts'] += (int) Arr::get($result, 'data.total', 0);
        $state['companies'] += count($companies);
        $state['failed'] += $result['ok'] ? 0 : 1;
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
}
