<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use RuntimeException;

class GhlContactSyncService
{
    public function __construct(
        private readonly GhlClient $client,
        private readonly GhlCampaignSegments $segments,
        private readonly GhlContactSyncPlanner $planner,
        private readonly GhlContactSyncCursor $cursorStore,
        private readonly GhlContactPagePersister $persister,
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
            $this->cursorStore->restart();
        }

        $cursor = $this->cursorStore->current($this->syncTags());
        $synced = 0;
        $processedPages = 0;

        try {
            while ($processedPages < GhlContactSyncPlanner::ChunkPageBudget) {
                if ($this->cursorStore->isComplete($cursor)) {
                    $this->completeChunkedSync($cursor);

                    return ['ok' => true, 'synced' => $synced, 'has_more' => false, 'error' => null];
                }

                $cursor = $this->prepareCurrentTag($cursor);

                if ($this->cursorStore->tagIsComplete($cursor)) {
                    $cursor = $this->cursorStore->advanceTag($cursor);
                    $this->cursorStore->store($cursor);

                    continue;
                }

                $result = $this->syncCursorPages($cursor, GhlContactSyncPlanner::ChunkPageBudget - $processedPages);
                $cursor = $result['cursor'];
                $synced += $result['synced'];
                $processedPages += $result['processed_pages'];

                if (! $result['ok']) {
                    $this->cursorStore->store($cursor);

                    return ['ok' => false, 'synced' => $synced, 'has_more' => true, 'error' => $result['error']];
                }

                $this->cursorStore->store($cursor);
            }
        } catch (RuntimeException $exception) {
            $this->cursorStore->store($cursor);

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

    private function prepareCurrentTag(array $cursor): array
    {
        if (is_array($cursor['ranges'])) {
            return $cursor;
        }

        $tag = $cursor['tags'][$cursor['tag_index']];

        return $this->cursorStore->ensureRanges($cursor, $this->planner->dateRangesForTag($tag));
    }

    /**
     * @return array{ok: bool, cursor: array, synced: int, processed_pages: int, error: string|null}
     */
    private function syncCursorPages(array $cursor, int $pageBudget): array
    {
        $tag = $cursor['tags'][$cursor['tag_index']];
        $fetches = $this->planner->pagesToFetch($cursor, $pageBudget);
        $results = $this->planner->fetchTagPages($tag, $cursor['ranges'], $fetches);
        $synced = 0;
        $processedPages = 0;

        while (! $this->cursorStore->tagIsComplete($cursor)) {
            $key = $this->planner->pageKey($cursor['range_index'], $cursor['page']);
            if (! array_key_exists($key, $results)) {
                break;
            }

            $result = $this->persister->applyPage(
                $tag,
                $cursor['ranges'][$cursor['range_index']],
                $cursor['page'],
                $results[$key],
            );

            if (! $result['ok']) {
                return [
                    'ok' => false,
                    'cursor' => $cursor,
                    'synced' => $synced,
                    'processed_pages' => $processedPages,
                    'error' => $result['error'],
                ];
            }

            $synced += $result['synced'];
            $processedPages++;
            $cursor = $this->cursorStore->advancePage($cursor, $result['is_last_page']);
        }

        return [
            'ok' => true,
            'cursor' => $cursor,
            'synced' => $synced,
            'processed_pages' => $processedPages,
            'error' => null,
        ];
    }

    private function completeChunkedSync(array $cursor): void
    {
        $this->persister->deleteContactsMissingFromFullSync(
            $cursor['tags'],
            Carbon::parse($cursor['run_started_at']),
        );
        $this->cursorStore->complete();
    }
}
