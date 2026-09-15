@if (session('ghl_sync_status'))
    <div class="rounded-md border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-900 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-100">
        <p class="font-medium">HighLevel full sync queued</p>
        <p class="mt-1 text-teal-800 dark:text-teal-200">{{ session('ghl_sync_status') }} Large tags are processed automatically in background batches; do not click Sync again while it is pending.</p>
    </div>
@endif

@if (($syncStatus['pending'] ?? 0) > 0)
    <div class="rounded-md border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-100">
        <p class="font-medium">HighLevel sync is waiting for the queue worker</p>
        <p class="mt-1 text-sky-800 dark:text-sky-200">{{ number_format($syncStatus['pending']) }} sync job{{ $syncStatus['pending'] === 1 ? '' : 's' }} pending on the ghl-sync queue.</p>
    </div>
@endif

@if (($syncStatus['failed'] ?? 0) > 0)
    <div class="rounded-md border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-100">
        <p class="font-medium">HighLevel sync job failed</p>
        <p class="mt-1 text-rose-800 dark:text-rose-200">{{ number_format($syncStatus['failed']) }} failed sync job{{ $syncStatus['failed'] === 1 ? '' : 's' }} found. The latest worker error should be checked before syncing again.</p>
    </div>
@endif

@if (session('ghl_sync_error'))
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-medium">HighLevel sync failed</p>
        <p class="mt-1 text-amber-800 dark:text-amber-200">{{ session('ghl_sync_error') }}</p>
    </div>
@endif

@if (! $dashboard['ok'] || $hasPartialFailure)
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-medium">HighLevel connection needs attention</p>
        <p class="mt-1 text-amber-800 dark:text-amber-200">{{ $dashboard['error'] }}</p>
    </div>
@endif
