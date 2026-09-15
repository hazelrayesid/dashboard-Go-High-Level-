<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class GhlContactSyncCursor
{
    private const CacheKey = 'ghl:contacts-sync:cursor';

    /**
     * @param  array<int, string>  $tags
     */
    public function current(array $tags): array
    {
        $cursor = Cache::get(self::CacheKey);

        if (is_array($cursor) && isset($cursor['tags'], $cursor['tag_index'], $cursor['run_started_at'])) {
            return $cursor;
        }

        return [
            'run_started_at' => now()->toISOString(),
            'tags' => $tags,
            'tag_index' => 0,
            'ranges' => null,
            'range_index' => 0,
            'page' => 1,
        ];
    }

    public function restart(): void
    {
        Cache::forget(self::CacheKey);
    }

    public function store(array $cursor): void
    {
        Cache::put(self::CacheKey, $cursor, now()->addHours(12));
    }

    public function complete(): void
    {
        Cache::forget(self::CacheKey);
        Cache::flush();
    }

    public function isComplete(array $cursor): bool
    {
        return $cursor['tag_index'] >= count($cursor['tags']);
    }

    public function tagIsComplete(array $cursor): bool
    {
        return is_array($cursor['ranges']) && $cursor['range_index'] >= count($cursor['ranges']);
    }

    public function ensureRanges(array $cursor, array $ranges): array
    {
        if (is_array($cursor['ranges'])) {
            return $cursor;
        }

        $cursor['ranges'] = $ranges;
        $cursor['range_index'] = 0;
        $cursor['page'] = 1;

        return $cursor;
    }

    public function advanceTag(array $cursor): array
    {
        $cursor['tag_index']++;
        $cursor['ranges'] = null;
        $cursor['range_index'] = 0;
        $cursor['page'] = 1;

        return $cursor;
    }

    public function advancePage(array $cursor, bool $isLastPage): array
    {
        if ($isLastPage) {
            $cursor['range_index']++;
            $cursor['page'] = 1;

            return $cursor;
        }

        $cursor['page']++;

        return $cursor;
    }
}
