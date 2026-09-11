@if (! $dashboard['ok'] || $hasPartialFailure)
    <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <p class="font-medium">HighLevel connection needs attention</p>
        <p class="mt-1 text-amber-800">{{ $dashboard['error'] }}</p>
    </div>
@endif
