<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class GhlSyncStatus
{
    private const CursorCacheKey = 'ghl:contacts-sync:cursor';

    /**
     * @return array{pending: int, failed: int, contacts: int, active_contacts: int, stale_contacts: int, is_running: bool, cursor: array<string, mixed>|null}
     */
    public function summary(): array
    {
        $cursor = $this->cursor();
        $pending = DB::table('jobs')->where('queue', 'ghl-sync')->count();
        $failed = DB::table('failed_jobs')->where('queue', 'ghl-sync')->count();
        $contacts = GhlContact::withTrashed()->count();
        $activeContacts = GhlContact::query()->count();

        return [
            'pending' => $pending,
            'failed' => $failed,
            'contacts' => $contacts,
            'active_contacts' => $activeContacts,
            'stale_contacts' => max($contacts - $activeContacts, 0),
            'is_running' => $pending > 0 || ($cursor !== null && $failed === 0),
            'cursor' => $cursor,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cursor(): ?array
    {
        $cursor = Cache::get(self::CursorCacheKey);

        if (! is_array($cursor) || ! isset($cursor['tags'], $cursor['tag_index'])) {
            return null;
        }

        $tagIndex = (int) $cursor['tag_index'];
        $tags = is_array($cursor['tags']) ? $cursor['tags'] : [];
        $ranges = is_array($cursor['ranges'] ?? null) ? $cursor['ranges'] : [];
        $rangeIndex = (int) ($cursor['range_index'] ?? 0);
        $range = $ranges[$rangeIndex] ?? null;

        return [
            'tag' => Arr::get($tags, $tagIndex),
            'tag_position' => min($tagIndex + 1, max(count($tags), 1)),
            'tag_total' => count($tags),
            'range_from' => is_array($range) ? Arr::get($range, 'from') : null,
            'range_to' => is_array($range) ? Arr::get($range, 'to') : null,
            'range_position' => $ranges === [] ? null : min($rangeIndex + 1, count($ranges)),
            'range_total' => count($ranges),
            'page' => (int) ($cursor['page'] ?? 1),
            'started_at' => $cursor['run_started_at'] ?? null,
        ];
    }
}
