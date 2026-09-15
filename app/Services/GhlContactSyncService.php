<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

class GhlContactSyncService
{
    private const PageLimit = 100;
    private const MaxPagesPerDateRange = 100;
    private const ChunkPageBudget = 100;
    private const CursorCacheKey = 'ghl:contacts-sync:cursor';

    public function __construct(
        private readonly GhlClient $client,
        private readonly GhlCampaignSegments $segments,
        private readonly GhlContactMapper $mapper,
    ) {}

    /**
     * @return array{ok: bool, synced: int, error: string|null}
     */
    public function sync(): array
    {
        $synced = 0;
        $restart = true;

        do {
            $result = $this->syncChunk($restart);
            $restart = false;
            $synced += $result['synced'];

            if (! $result['ok']) {
                return ['ok' => false, 'synced' => $synced, 'error' => $result['error']];
            }
        } while ($result['has_more']);

        return ['ok' => true, 'synced' => $synced, 'error' => null];
    }

    /**
     * @return array{ok: bool, synced: int, has_more: bool, error: string|null}
     */
    public function syncChunk(bool $restart = false): array
    {
        if (! $this->client->isConfigured()) {
            return ['ok' => false, 'synced' => 0, 'has_more' => false, 'error' => 'GHL credentials are not configured.'];
        }

        if ($restart) {
            Cache::forget(self::CursorCacheKey);
        }

        $cursor = $this->syncCursor();
        $synced = 0;
        $processedPages = 0;

        try {
            while ($processedPages < self::ChunkPageBudget) {
                if ($this->cursorIsComplete($cursor)) {
                    $this->completeChunkedSync($cursor);

                    return ['ok' => true, 'synced' => $synced, 'has_more' => false, 'error' => null];
                }

                $tag = $cursor['tags'][$cursor['tag_index']];
                $cursor = $this->ensureCursorRanges($cursor, $tag);

                if ($this->cursorTagIsComplete($cursor)) {
                    $cursor = $this->advanceCursorTag($cursor);
                    $this->storeCursor($cursor);

                    continue;
                }

                // Pages are fetched concurrently (spanning date ranges so the pool stays
                // full) but applied in cursor order, so the cursor always points at the
                // first page that is not yet in the database.
                $fetches = $this->pagesToFetch($cursor, self::ChunkPageBudget - $processedPages);
                $results = $this->fetchTagPages($tag, $cursor['ranges'], $fetches);

                while (! $this->cursorTagIsComplete($cursor)) {
                    $key = $this->pageKey($cursor['range_index'], $cursor['page']);
                    if (! array_key_exists($key, $results)) {
                        // The range ended earlier than its stored total predicted; refetch from the cursor.
                        break;
                    }

                    $result = $this->applyPage($tag, $cursor['ranges'][$cursor['range_index']], $cursor['page'], $results[$key]);

                    if (! $result['ok']) {
                        $this->storeCursor($cursor);

                        return ['ok' => false, 'synced' => $synced, 'has_more' => true, 'error' => $result['error']];
                    }

                    $synced += $result['synced'];
                    $processedPages++;

                    $cursor = $this->advanceCursorPage($cursor, $result['is_last_page']);
                }

                $this->storeCursor($cursor);
            }
        } catch (RuntimeException $exception) {
            $this->storeCursor($cursor);

            return ['ok' => false, 'synced' => $synced, 'has_more' => true, 'error' => $exception->getMessage()];
        }

        return ['ok' => true, 'synced' => $synced, 'has_more' => true, 'error' => null];
    }

    /**
     * @return array<int, string>
     */
    private function syncTags(): array
    {
        return collect($this->segments->groups())
            ->flatMap(fn (array $segments): array => $segments)
            ->flatMap(fn (array $segment): array => $this->segments->tags($segment))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array{ok: bool, synced: int, error: string|null}
     */
    private function syncTag(string $tag): array
    {
        $synced = 0;

        foreach ($this->dateRangesForTag($tag) as $dateRange) {
            $result = $this->syncTagDateRange($tag, $dateRange);

            if (! $result['ok']) {
                return ['ok' => false, 'synced' => $synced, 'error' => $result['error']];
            }

            $synced += $result['synced'];
        }

        return ['ok' => true, 'synced' => $synced, 'error' => null];
    }

    /**
     * GHL limits search pagination to 100 pages. Split large tags into
     * non-overlapping date windows before reading their pages.
     *
     * @return array<int, array{from: string, to: string}>
     */
    private function dateRangesForTag(string $tag): array
    {
        return $this->splitDateRange(
            $tag,
            '1970-01-01T00:00:00.000Z',
            now()->addSecond()->toISOString(),
        );
    }

    /**
     * Binary-split the window until every piece holds at most 10,000 contacts.
     * All pending windows of one depth are counted in a single concurrent
     * batch; the resulting ranges stay in chronological order.
     *
     * @return array<int, array{from: string, to: string, total: int}>
     */
    private function splitDateRange(string $tag, string $from, string $to): array
    {
        $nodes = [['from' => $from, 'to' => $to, 'total' => null]];

        while (($pending = array_keys(array_filter($nodes, fn (array $node): bool => $node['total'] === null))) !== []) {
            $requests = [];
            foreach ($pending as $index) {
                $requests[$index] = ['page' => 1, 'dateRange' => ['from' => $nodes[$index]['from'], 'to' => $nodes[$index]['to']]];
            }

            $results = $this->client->contactsByTagBatch($tag, 1, $requests);

            $next = [];
            foreach ($nodes as $index => $node) {
                if ($node['total'] !== null) {
                    $next[] = $node;

                    continue;
                }

                $result = $results[$index] ?? ['ok' => false, 'error' => null];
                if (! $result['ok']) {
                    throw new RuntimeException($result['error'] ?? "Unable to count GHL contacts for tag [$tag].");
                }

                $total = (int) Arr::get($result, 'data.total', 0);
                if ($total <= self::PageLimit * self::MaxPagesPerDateRange) {
                    if ($total > 0) {
                        $next[] = ['from' => $node['from'], 'to' => $node['to'], 'total' => $total];
                    }

                    continue;
                }

                $start = Carbon::parse($node['from']);
                $end = Carbon::parse($node['to']);
                if ($start->diffInMilliseconds($end) < 1) {
                    throw new RuntimeException("GHL returned more than 10,000 contacts in a one-millisecond range for tag [$tag].");
                }

                $midpoint = $start->copy()->addMilliseconds((int) floor($start->diffInMilliseconds($end) / 2));
                $next[] = ['from' => $node['from'], 'to' => $midpoint->toISOString(), 'total' => null];
                $next[] = ['from' => $midpoint->copy()->addMillisecond()->toISOString(), 'to' => $node['to'], 'total' => null];
            }

            $nodes = $next;
        }

        return $nodes;
    }

    /**
     * Pages to request in one concurrent batch, starting at the cursor and
     * continuing into the following date ranges so the pool is always full.
     * Never past the last page of a range when its total is known, and never
     * past the chunk budget.
     *
     * @param  array{ranges: array<int, array{from: string, to: string, total?: int}>, range_index: int, page: int}  $cursor
     * @return array<int, array{range_index: int, page: int}>
     */
    private function pagesToFetch(array $cursor, int $budget): array
    {
        $wanted = max(1, min($this->concurrency(), $budget));
        $fetches = [];
        $page = $cursor['page'];

        for ($rangeIndex = $cursor['range_index']; $rangeIndex < count($cursor['ranges']) && count($fetches) < $wanted; $rangeIndex++) {
            $lastPage = $this->lastPageOfRange($cursor['ranges'][$rangeIndex]);

            for (; $page <= $lastPage && count($fetches) < $wanted; $page++) {
                $fetches[] = ['range_index' => $rangeIndex, 'page' => $page];
            }

            if (! isset($cursor['ranges'][$rangeIndex]['total'])) {
                // Unknown size: stay within this range until its real last page is seen.
                break;
            }

            $page = 1;
        }

        return $fetches;
    }

    /**
     * @param  array{from: string, to: string, total?: int}  $dateRange
     */
    private function lastPageOfRange(array $dateRange): int
    {
        $total = (int) ($dateRange['total'] ?? 0);

        return $total > 0
            ? max(1, min(self::MaxPagesPerDateRange, (int) ceil($total / self::PageLimit)))
            : self::MaxPagesPerDateRange;
    }

    private function pageKey(int $rangeIndex, int $page): string
    {
        return $rangeIndex.':'.$page;
    }

    private function concurrency(): int
    {
        return max(1, (int) config('services.ghl.sync_concurrency', 10));
    }

    /**
     * @param  array{from: string, to: string}  $dateRange
     * @return array{ok: bool, synced: int, error: string|null}
     */
    private function syncTagDateRange(string $tag, array $dateRange): array
    {
        $synced = 0;

        for ($page = 1; true; $page++) {
            // A full sync must never read a short-lived dashboard cache.
            $result = $this->client->contactsByTagPage(
                $tag,
                self::PageLimit,
                page: $page,
                dateRange: $dateRange,
                useCache: false,
            );

            if (! $result['ok']) {
                return [
                    'ok' => false,
                    'synced' => $synced,
                    'error' => $result['error']." Tag [$tag], range {$dateRange['from']} to {$dateRange['to']}, page [$page].",
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

            $synced += count($rows);
            if ($this->isLastPage($result, $contacts->count(), $page)) {
                break;
            }

            if ($page >= self::MaxPagesPerDateRange) {
                return ['ok' => false, 'synced' => $synced, 'error' => "GHL pagination exceeded 100 pages for tag [$tag]."];
            }
        }

        return ['ok' => true, 'synced' => $synced, 'error' => null];
    }
    /**
     * Fetch the requested pages concurrently. Results are keyed by range index
     * and page so the caller can apply them in cursor order.
     *
     * @param  array<int, array{from: string, to: string, total?: int}>  $ranges
     * @param  array<int, array{range_index: int, page: int}>  $fetches
     * @return array<string, array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}>
     */
    private function fetchTagPages(string $tag, array $ranges, array $fetches): array
    {
        $requests = [];
        foreach ($fetches as $fetch) {
            $range = $ranges[$fetch['range_index']];
            $requests[$this->pageKey($fetch['range_index'], $fetch['page'])] = [
                'page' => $fetch['page'],
                'dateRange' => ['from' => $range['from'], 'to' => $range['to']],
            ];
        }

        return $this->client->contactsByTagBatch($tag, self::PageLimit, $requests);
    }

    /**
     * Upsert one fetched page into the database.
     *
     * @param  array{from: string, to: string, total?: int}  $dateRange
     * @param  array{ok: bool, status?: int|null, data?: array<string, mixed>|null, error?: string|null}  $result
     * @return array{ok: bool, synced: int, is_last_page: bool, error: string|null}
     */
    private function applyPage(string $tag, array $dateRange, int $page, array $result): array
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
     * Remove stale rows only after every dashboard tag has completed.
     * Partial or failed runs therefore never delete valid previous data.
     *
     * @param  array<int, string>  $tags
     */
    private function deleteContactsMissingFromFullSync(array $tags, Carbon $runStartedAt): void
    {
        GhlContact::withTrashed()
            ->where('synced_at', '<', $runStartedAt)
            ->where(fn (Builder $query): Builder => collect($tags)
                ->reduce(fn (Builder $query, string $tag): Builder => $query->orWhereJsonContains('tags', $tag), $query))
            ->forceDelete();
    }

    /**
     * @return array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}
     */
    private function syncCursor(): array
    {
        $cursor = Cache::get(self::CursorCacheKey);

        if (is_array($cursor) && isset($cursor['tags'], $cursor['tag_index'], $cursor['run_started_at'])) {
            return $cursor;
        }

        return [
            'run_started_at' => now()->toISOString(),
            'tags' => $this->syncTags(),
            'tag_index' => 0,
            'ranges' => null,
            'range_index' => 0,
            'page' => 1,
        ];
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     */
    private function storeCursor(array $cursor): void
    {
        Cache::put(self::CursorCacheKey, $cursor, now()->addHours(12));
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     */
    private function cursorIsComplete(array $cursor): bool
    {
        return $cursor['tag_index'] >= count($cursor['tags']);
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     */
    private function cursorTagIsComplete(array $cursor): bool
    {
        return is_array($cursor['ranges']) && $cursor['range_index'] >= count($cursor['ranges']);
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     * @return array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}
     */
    private function ensureCursorRanges(array $cursor, string $tag): array
    {
        if (is_array($cursor['ranges'])) {
            return $cursor;
        }

        $cursor['ranges'] = $this->dateRangesForTag($tag);
        $cursor['range_index'] = 0;
        $cursor['page'] = 1;

        return $cursor;
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     * @return array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}
     */
    private function advanceCursorTag(array $cursor): array
    {
        $cursor['tag_index']++;
        $cursor['ranges'] = null;
        $cursor['range_index'] = 0;
        $cursor['page'] = 1;

        return $cursor;
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     * @return array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}
     */
    private function advanceCursorPage(array $cursor, bool $isLastPage): array
    {
        if ($isLastPage) {
            $cursor['range_index']++;
            $cursor['page'] = 1;

            return $cursor;
        }

        $cursor['page']++;

        return $cursor;
    }

    /**
     * @param  array{run_started_at: string, tags: array<int, string>, tag_index: int, ranges: array<int, array{from: string, to: string}>|null, range_index: int, page: int}  $cursor
     */
    private function completeChunkedSync(array $cursor): void
    {
        $this->deleteContactsMissingFromFullSync($cursor['tags'], Carbon::parse($cursor['run_started_at']));
        Cache::forget(self::CursorCacheKey);
        Cache::flush();
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
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
            return $page >= (int) ceil($total / self::PageLimit);
        }

        return $contactCount < self::PageLimit;
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function contactId(array $contact): ?string
    {
        $id = Arr::get($contact, 'id')
            ?? Arr::get($contact, 'contactId')
            ?? Arr::get($contact, 'contact_id');

        return filled($id) ? (string) $id : null;
    }
}
