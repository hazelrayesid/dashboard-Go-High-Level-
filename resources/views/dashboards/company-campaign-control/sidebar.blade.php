@php
    $sidebarIcons = [
        'Sent, has website' => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10"/><path d="m15 11 2 2 4-4"/></svg>',
        'Sent, no website' => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10"/><path d="m16 8 5 5"/><path d="m21 8-5 5"/></svg>',
        'Report opened' => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="m9 15 2 2 4-4"/></svg>',
        'Remaining' => '<svg viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.1 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.5-6.9A2 2 0 0 0 16.7 4H7.3a2 2 0 0 0-1.8 1.1z"/></svg>',
    ];
@endphp

<aside data-sidebar class="border-b border-slate-200 bg-white transition-all duration-200 dark:border-slate-800 dark:bg-slate-950 xl:sticky xl:top-0 xl:h-screen xl:border-b-0 xl:border-r">
    <div class="flex h-full flex-col gap-6 p-4">
        <div class="flex items-center justify-between gap-3">
            <div data-sidebar-content class="min-w-0">
                <p class="text-sm font-semibold text-teal-700 dark:text-teal-300">Top4 Marketing</p>
                <h1 class="mt-1 truncate text-lg font-semibold leading-tight tracking-normal text-slate-950 dark:text-white">Campaign Control</h1>
            </div>

            <button type="button" data-sidebar-toggle aria-label="Toggle sidebar" aria-expanded="true" title="Toggle sidebar" class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">
                <svg data-sidebar-open-icon viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 5h18"/>
                    <path d="M3 12h18"/>
                    <path d="M3 19h18"/>
                    <path d="m15 9-3 3 3 3"/>
                </svg>
                <svg data-sidebar-closed-icon viewBox="0 0 24 24" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 5h18"/>
                    <path d="M3 12h18"/>
                    <path d="M3 19h18"/>
                    <path d="m9 9 3 3-3 3"/>
                </svg>
            </button>
        </div>

        <div title="{{ $hasPartialFailure ? 'Partial sync' : ($dashboard['ok'] ? 'GHL connected' : 'GHL needs attention') }}" class="rounded-md border px-3 py-2 text-sm font-medium {{ $dashboard['ok'] && ! $hasPartialFailure ? 'border-teal-200 bg-teal-50 text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-200' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200' }}">
            <div class="flex items-center gap-3">
                <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"/>
                </svg>
                <span data-sidebar-label class="truncate">{{ $hasPartialFailure ? 'Partial sync' : ($dashboard['ok'] ? 'GHL connected' : 'GHL needs attention') }}</span>
            </div>
        </div>

        <nav class="grid gap-1" aria-label="Campaign sections">
            @foreach ($groups as $groupName => $segments)
                @php
                    $groupKey = str($groupName)->lower()->replace([',', ' '], ['', '-']);
                @endphp

                <button type="button" data-filter-button="{{ $groupKey }}" title="{{ $groupName }}" class="group flex items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-950 data-[active=true]:bg-slate-950 data-[active=true]:text-white dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-white dark:data-[active=true]:bg-white dark:data-[active=true]:text-slate-950">
                    <span class="flex min-w-0 items-center gap-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-500 group-data-[active=true]:border-white/10 group-data-[active=true]:bg-white/10 group-data-[active=true]:text-white dark:border-slate-800 dark:bg-slate-900 dark:text-slate-400 dark:group-data-[active=true]:border-slate-950/10 dark:group-data-[active=true]:bg-slate-950/10 dark:group-data-[active=true]:text-slate-950">
                            {!! $sidebarIcons[$groupName] ?? $sidebarIcons['Remaining'] !!}
                        </span>
                        <span data-sidebar-label class="truncate">{{ $groupName }}</span>
                    </span>
                    <span data-sidebar-label class="text-xs opacity-70">{{ number_format(collect($segments)->sum('total')) }}</span>
                </button>
            @endforeach
        </nav>

        <div class="mt-auto grid gap-2">
            <form method="POST" action="{{ route('ghl.sync') }}">
                @csrf
                <button type="submit" title="Run one full GHL sync to the database. Automatic batches are handled in the background." class="flex w-full items-center justify-center gap-2 rounded-md bg-slate-950 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M21 12a9 9 0 0 1-15.6 6"/>
                        <path d="M3 12A9 9 0 0 1 18.6 6"/>
                        <path d="M3 18h6"/>
                        <path d="M21 6h-6"/>
                    </svg>
                    <span data-sidebar-label>Sync GHL data</span>
                </button>
            </form>
            <p data-sidebar-content class="px-1 text-center text-[11px] leading-4 text-slate-500 dark:text-slate-400">
                One click runs the full sync. Large tags are split automatically into background batches.
            </p>
            <a href="https://app.gohighlevel.com/" target="_blank" rel="noreferrer" title="Open GHL" class="flex items-center justify-center gap-2 rounded-md border border-slate-200 px-3 py-2 text-center text-sm font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">
                <svg viewBox="0 0 24 24" class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M15 3h6v6"/>
                    <path d="M10 14 21 3"/>
                    <path d="M21 14v5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5"/>
                </svg>
                <span data-sidebar-label>Open GHL</span>
            </a>
        </div>
    </div>
</aside>
