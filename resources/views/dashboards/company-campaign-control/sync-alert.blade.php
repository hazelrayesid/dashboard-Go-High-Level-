@if (! $dashboard['ok'] || $hasPartialFailure)
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
        <p class="font-medium">HighLevel connection needs attention</p>
        <p class="mt-1 text-amber-800 dark:text-amber-200">{{ $dashboard['error'] }}</p>
    </div>
@endif
