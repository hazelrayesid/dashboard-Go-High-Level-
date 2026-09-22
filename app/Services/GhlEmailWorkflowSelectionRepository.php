<?php

namespace App\Services;

use App\Models\GhlEmailStatWorkflow;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Throwable;

class GhlEmailWorkflowSelectionRepository
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $fallbackCampaigns = [];

    private const DEFAULT_ENABLED_WORKFLOW_NAMES = [
        'Followup after open - website demo',
        'Top4 Signup - Has Website - Styled',
        'Top4 Signup - No Website - Styled',
        'Top4 Signup - No Website - Plain',
        'No Website - Plain',
        'Followup after open',
        'Has Website - Plain',
        'Has Website - Styled',
        'No Website - Styled',
        'Top4 Signup - Has Website - Plain',
    ];

    /**
     * @param  array<int, array<string, mixed>>  $campaigns
     */
    public function syncFromCampaigns(array $campaigns): void
    {
        $this->fallbackCampaigns = collect($campaigns)
            ->filter(fn (array $campaign): bool => filled(Arr::get($campaign, 'id')))
            ->unique(fn (array $campaign): string => (string) Arr::get($campaign, 'id'))
            ->values()
            ->all();

        collect($campaigns)
            ->filter(fn (array $campaign): bool => filled(Arr::get($campaign, 'id')))
            ->unique(fn (array $campaign): string => (string) Arr::get($campaign, 'id'))
            ->each(function (array $campaign): void {
                $workflowId = (string) Arr::get($campaign, 'id');
                $name = (string) (Arr::get($campaign, 'name') ?: 'Workflow Campaign');

                try {
                    $existing = GhlEmailStatWorkflow::query()
                        ->where('workflow_id', $workflowId)
                        ->first();

                    GhlEmailStatWorkflow::query()->updateOrCreate(
                        ['workflow_id' => $workflowId],
                        [
                            'source_id' => $this->sourceId($campaign),
                            'name' => $name,
                            'status' => (string) (Arr::get($campaign, 'status') ?: ''),
                            'enabled' => $existing?->enabled ?? $this->isDefaultEnabled($name),
                            'sort_order' => $existing?->sort_order ?? $this->defaultSortOrder($name),
                            'last_seen_at' => now(),
                        ],
                    );
                } catch (Throwable) {
                    // Keep the dashboard usable if migrations are pending or the test DB driver is unavailable.
                }
            });
    }

    /**
     * @return Collection<int, GhlEmailStatWorkflow>
     */
    public function selected(): Collection
    {
        try {
            return $this->baseQuery()
                ->where('enabled', true)
                ->get();
        } catch (Throwable) {
            return collect();
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function options(): array
    {
        try {
            return $this->baseQuery()
                ->get()
                ->map(fn (GhlEmailStatWorkflow $workflow): array => [
                    'id' => $workflow->workflow_id,
                    'name' => $workflow->name,
                    'status' => $workflow->status,
                    'enabled' => $workflow->enabled,
                    'last_seen_at' => $workflow->last_seen_at?->toDateTimeString(),
                ])
                ->all();
        } catch (Throwable) {
            return $this->fallbackOptions();
        }
    }

    /**
     * @param  array<int, string>  $workflowIds
     */
    public function saveSelection(array $workflowIds): void
    {
        $ids = collect($workflowIds)
            ->filter(fn (mixed $id): bool => is_scalar($id) && filled((string) $id))
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values();

        try {
            GhlEmailStatWorkflow::query()->update(['enabled' => false]);

            $ids->each(function (string $workflowId, int $index): void {
                GhlEmailStatWorkflow::query()
                    ->where('workflow_id', $workflowId)
                    ->update([
                        'enabled' => true,
                        'sort_order' => $index + 1,
                    ]);
            });
        } catch (Throwable) {
            // Selection persistence requires the workflow table; ignore only when storage is unavailable.
        }
    }

    public function selectionFingerprint(): string
    {
        return hash('sha256', $this->selected()
            ->map(fn (GhlEmailStatWorkflow $workflow): string => $workflow->workflow_id.':'.$workflow->updated_at?->timestamp)
            ->implode('|'));
    }

    /**
     * @return array<int, string>
     */
    public function selectedNames(): array
    {
        return $this->selected()
            ->pluck('name')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function selectedCampaigns(): array
    {
        $selected = $this->selected();

        if ($selected->isEmpty() && $this->fallbackCampaigns !== []) {
            return collect($this->fallbackCampaigns)
                ->filter(fn (array $campaign): bool => $this->isDefaultEnabled((string) Arr::get($campaign, 'name')))
                ->sortBy(fn (array $campaign): int => $this->defaultSortOrder((string) Arr::get($campaign, 'name')))
                ->values()
                ->all();
        }

        return $selected
            ->map(fn (GhlEmailStatWorkflow $workflow): array => [
                'id' => $workflow->workflow_id,
                'sourceId' => $workflow->source_id,
                'name' => $workflow->name,
                'status' => $workflow->status,
                'updatedAt' => $workflow->updated_at?->toISOString(),
            ])
            ->all();
    }

    private function baseQuery()
    {
        return GhlEmailStatWorkflow::query()
            ->orderBy('sort_order')
            ->orderByDesc('enabled')
            ->orderBy('name');
    }

    private function isDefaultEnabled(string $name): bool
    {
        return in_array($name, self::DEFAULT_ENABLED_WORKFLOW_NAMES, true);
    }

    private function defaultSortOrder(string $name): int
    {
        $position = array_search($name, self::DEFAULT_ENABLED_WORKFLOW_NAMES, true);

        return $position === false ? 999 : $position + 1;
    }

    private function sourceId(array $campaign): string
    {
        return (string) (Arr::get($campaign, 'sourceId') ?: Arr::get($campaign, 'id') ?: Arr::get($campaign, '_id') ?: '');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fallbackOptions(): array
    {
        return collect($this->fallbackCampaigns)
            ->map(fn (array $campaign): array => [
                'id' => (string) Arr::get($campaign, 'id'),
                'name' => (string) (Arr::get($campaign, 'name') ?: 'Workflow Campaign'),
                'status' => (string) (Arr::get($campaign, 'status') ?: ''),
                'enabled' => $this->isDefaultEnabled((string) Arr::get($campaign, 'name')),
                'last_seen_at' => null,
            ])
            ->sortBy(fn (array $campaign): int => $this->defaultSortOrder($campaign['name']))
            ->values()
            ->all();
    }
}
