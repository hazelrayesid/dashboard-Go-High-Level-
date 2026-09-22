<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class GhlEmailStatsService
{
    public function __construct(
        private readonly GhlEmailStatsClient $client,
        private readonly GhlEmailStatsStateBuilder $stateBuilder,
        private readonly GhlEmailWorkflowSelectionRepository $workflowSelection,
    ) {}

    public function dashboardState(array $range): array
    {
        $window = $this->stateBuilder->normalizedRange($range);
        $empty = $this->stateBuilder->emptyState($window);

        if (! $this->isConfigured()) {
            return array_merge($empty, [
                'available' => false,
                'error' => 'GHL_EMAIL_STATS_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ]);
        }

        $this->client->startBudget((int) config('services.ghl.email_stats_budget'));
        $campaignsResult = $this->workflowCampaigns();

        if ($campaignsResult['ok']) {
            $this->workflowSelection->syncFromCampaigns($campaignsResult['campaigns']);
        }

        $workflowOptions = $this->workflowSelection->options();
        $selectedWorkflows = $this->workflowSelection->selectedCampaigns();
        $selectedNames = $this->workflowSelection->selectedNames();

        if (! $campaignsResult['ok'] && $selectedWorkflows === []) {
            return array_merge($empty, [
                'available' => false,
                'error' => $campaignsResult['error'],
                'workflow_options' => $workflowOptions,
            ]);
        }

        $cacheKey = 'ghl:email-stats:'.hash('sha256', json_encode([
            'base_url' => config('services.ghl.base_url'),
            'location_id' => config('services.ghl.location_id'),
            'version' => config('services.ghl.email_stats_version'),
            'range' => $window,
            'selected' => $this->workflowSelection->selectionFingerprint(),
        ]));

        $state = Cache::remember($cacheKey, now()->addMinute(), function () use ($window, $empty, $selectedWorkflows, $selectedNames): array {
            return $this->stateBuilder->build($this->workflowStatsItems($selectedWorkflows, $window), $empty, $selectedNames);
        });

        return array_merge($state, [
            'workflow_options' => $workflowOptions,
            'selected_workflows_count' => count($selectedWorkflows),
            'workflow_list_available' => $campaignsResult['ok'],
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $workflows
     */
    private function workflowStatsItems(array $workflows, array $window): array
    {
        if ($workflows === []) {
            return ['ok' => true, 'items' => [], 'error' => null, 'complete' => true];
        }

        return [
            'ok' => true,
            'items' => collect($workflows)
                ->map(fn (array $workflow): array => $this->workflowSummaryStatsItem($workflow, $window))
                ->values()
                ->all(),
            'breakdown_placeholders' => collect($workflows)
                ->map(fn (array $workflow): array => $this->emptyWorkflowBreakdownRow($workflow))
                ->values()
                ->all(),
            'error' => null,
            'complete' => true,
        ];
    }

    private function workflowCampaigns(): array
    {
        $campaigns = [];

        foreach ([null, 'published'] as $status) {
            $result = $this->client->listWorkflows($status);

            if (! $result['ok']) {
                return $result;
            }

            $campaigns = array_merge($campaigns, $result['campaigns']);
        }

        return [
            'ok' => true,
            'campaigns' => collect($campaigns)
                ->unique(fn (array $campaign): string => (string) Arr::get($campaign, 'id'))
                ->values()
                ->all(),
            'error' => null,
        ];
    }

    private function workflowSummaryStatsItem(array $workflow, array $window): array
    {
        $workflowId = (string) Arr::get($workflow, 'id');
        $sourceId = $this->statsSourceId($workflow);

        return [
            'campaign_key' => 'workflow-campaigns:'.$workflowId,
            'date' => $this->pointDate($this->campaignDate($workflow), $window),
            'campaign_name' => (string) (Arr::get($workflow, 'name') ?: 'Workflow Campaign'),
            'email_name' => 'All workflow emails',
            'source_label' => 'Workflow Campaign',
            'stats' => blank($sourceId)
                ? ['ok' => false, 'stats' => [], 'error' => 'Workflow source id is missing.']
                : $this->client->campaignStats('workflow-campaigns', $sourceId),
        ];
    }

    private function emptyWorkflowBreakdownRow(array $workflow): array
    {
        return [
            'campaign_name' => (string) (Arr::get($workflow, 'name') ?: 'Workflow Campaign'),
            'email_name' => (string) (Arr::get($workflow, 'status') ?: 'No stats loaded'),
            'source_label' => 'Workflow Campaign',
            'delivered' => 0,
            'opened' => 0,
            'clicked' => 0,
            'soft_bounced' => 0,
            'hard_bounced' => 0,
            'bounced' => 0,
            'unsubscribed' => 0,
            'spam_complaints' => 0,
            'replied' => 0,
            'open_rate' => 0,
            'click_rate' => 0,
        ];
    }

    private function campaignDate(array $campaign): string
    {
        $date = Arr::get($campaign, 'sentAt')
            ?: Arr::get($campaign, 'scheduledAt')
            ?: Arr::get($campaign, 'updatedAt')
            ?: Arr::get($campaign, 'createdAt')
            ?: now()->toDateString();

        return CarbonImmutable::parse($date)->toDateString();
    }

    private function pointDate(string $date, array $window): string
    {
        if ($date < $window['from']) {
            return $window['from'];
        }

        if ($date > $window['to']) {
            return $window['to'];
        }

        return $date;
    }

    private function statsSourceId(array $campaign): string
    {
        return (string) (Arr::get($campaign, 'sourceId') ?: Arr::get($campaign, 'id') ?: Arr::get($campaign, '_id') ?: '');
    }

    private function isConfigured(): bool
    {
        return filled(config('services.ghl.email_stats_access_token')) && filled(config('services.ghl.location_id'));
    }
}
