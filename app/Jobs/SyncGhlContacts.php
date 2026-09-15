<?php

namespace App\Jobs;

use App\Services\GhlContactSyncService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class SyncGhlContacts implements ShouldQueue
{
    use Queueable;

    private const LockKey = 'ghl:contacts-sync:lock';

    public int $timeout = 900;

    public int $tries = 3;

    public array $backoff = [30, 90, 180];

    public function __construct(
        private readonly bool $restart = false,
    ) {}

    public function handle(GhlContactSyncService $syncService): void
    {
        // Only one chunk may run at a time. Two workers (or the scheduler firing
        // while a manual sync runs) would otherwise share one cursor, fetch the
        // same pages twice, and leave a second job chain that restarts a full
        // sync from scratch once the first one completes. A manual restart waits
        // for the running chunk to finish; any other overlapping job is dropped.
        $lock = Cache::lock(self::LockKey, $this->timeout + 60);

        try {
            $acquired = $this->restart ? $lock->block(120) : $lock->get();
        } catch (LockTimeoutException) {
            $acquired = false;
        }

        if (! $acquired) {
            if ($this->restart) {
                throw new RuntimeException('Another HighLevel sync chunk is still running; the restart will be retried.');
            }

            return;
        }

        try {
            $result = $syncService->syncChunk(restart: $this->restart);
        } finally {
            $lock->release();
        }

        if (! $result['ok']) {
            throw new RuntimeException($result['error'] ?? 'GHL contact sync failed.');
        }

        if ($result['has_more']) {
            self::dispatch()->onQueue('ghl-sync');
        }
    }
}
