<header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 px-4 py-4 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500 dark:text-slate-400">Live production data from HighLevel</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-normal text-slate-950 dark:text-white">Campaign workspace</h2>
        </div>

        <div class="flex max-w-full items-center gap-2 overflow-x-auto pb-1">
            <div class="flex rounded-md border border-slate-200 bg-slate-100 p-1 shadow-sm dark:border-slate-800 dark:bg-slate-900" aria-label="Quick filters">
                @foreach ($filterOptions as $option)
                    <button type="button" data-filter-button="{{ $option['filter'] }}" class="shrink-0 rounded-[5px] px-3.5 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-white hover:text-slate-950 data-[active=true]:bg-slate-950 data-[active=true]:text-white data-[active=true]:shadow-sm dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-white dark:data-[active=true]:bg-white dark:data-[active=true]:text-slate-950" @if ($option['filter'] === 'all') data-active="true" @endif>
                        {{ $option['label'] }}
                    </button>
                @endforeach
            </div>

            <button type="button" data-theme-toggle aria-label="Toggle theme" title="Toggle theme" class="grid h-10 w-10 shrink-0 place-items-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">
                <svg data-theme-light-icon viewBox="0 0 24 24" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2"/>
                    <path d="M12 20v2"/>
                    <path d="m4.93 4.93 1.41 1.41"/>
                    <path d="m17.66 17.66 1.41 1.41"/>
                    <path d="M2 12h2"/>
                    <path d="M20 12h2"/>
                    <path d="m6.34 17.66-1.41 1.41"/>
                    <path d="m19.07 4.93-1.41 1.41"/>
                </svg>
                <svg data-theme-dark-icon viewBox="0 0 24 24" class="hidden h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20.99 12.77A9 9 0 1 1 11.23 3a7 7 0 0 0 9.76 9.77z"/>
                </svg>
            </button>
        </div>
    </div>
</header>
