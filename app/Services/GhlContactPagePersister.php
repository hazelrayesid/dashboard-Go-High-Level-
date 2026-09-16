<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;

class GhlContactPagePersister
{
    public function __construct(private readonly GhlContactMapper $mapper) {}

    /**
     * @param  array{from: string, to: string, total?: int}  $dateRange
     * @param  array{ok: bool, status?: int|null, data?: array<string, mixed>|null, error?: string|null}  $result
     * @return array{ok: bool, synced: int, is_last_page: bool, error: string|null}
     */
    public function applyPage(string $tag, array $dateRange, int $page, array $result): array
    {
        if (! $result['ok']) {
            return [
                'ok' => false,
                'synced' => 0,
                'is_last_page' => false,
                'error' => ($result['error'] ?? 'HighLevel returned no response.')." Tag [$tag], range {$dateRange['from']} to {$dateRange['to']}, page [$page].",
            ];
        }

        $contacts = collect(Arr::get($result, 'data.contacts', []))
            ->filter(fn (mixed $contact): bool => is_array($contact) && filled($this->contactId($contact)))
            ->values();
        $rows = $contacts->map(fn (array $contact): array => $this->upsertRow($contact))->all();

        if ($rows !== []) {
            DB::transaction(fn (): int => GhlContact::query()->upsert(
                $rows,
                ['ghl_contact_id'],
                [
                    'name', 'email', 'phone', 'business', 'website', 'created_at_ghl',
                    'created_date', 'tags', 'custom_fields', 'audit_report_url',
                    'raw_payload', 'synced_at', 'deleted_at', 'updated_at',
                ],
            ));
        }

        return [
            'ok' => true,
            'synced' => count($rows),
            'is_last_page' => $this->isLastPage($result, $contacts->count(), $page),
            'error' => null,
        ];
    }

    /**
     * @param  array<int, string>  $tags
     */
    public function pruneContactsMissingFromFullSync(array $tags, Carbon $runStartedAt): void
    {
        GhlContact::withTrashed()
            ->where('synced_at', '<', $runStartedAt)
            ->where(fn (Builder $query): Builder => collect($tags)
                ->reduce(fn (Builder $query, string $tag): Builder => $query->orWhereJsonContains('tags', $tag), $query))
            ->delete();
    }

    private function upsertRow(array $contact): array
    {
        $row = $this->mapper->databaseRow($contact);

        $row['tags'] = $this->json($row['tags']);
        $row['custom_fields'] = $this->json($row['custom_fields']);
        $row['raw_payload'] = $this->json($row['raw_payload']);
        $row['deleted_at'] = null;

        return $row;
    }

    private function json(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        try {
            return json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * @param  array{data?: array<string, mixed>|null}  $result
     */
    private function isLastPage(array $result, int $contactCount, int $page): bool
    {
        $total = (int) Arr::get($result, 'data.total', 0);

        if ($total > 0) {
            return $page >= (int) ceil($total / GhlContactSyncPlanner::PageLimit);
        }

        return $contactCount < GhlContactSyncPlanner::PageLimit;
    }

    private function contactId(array $contact): ?string
    {
        $id = Arr::get($contact, 'id')
            ?? Arr::get($contact, 'contactId')
            ?? Arr::get($contact, 'contact_id');

        return filled($id) ? (string) $id : null;
    }
}
