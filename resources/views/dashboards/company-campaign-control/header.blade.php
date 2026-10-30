<header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 px-4 py-3 backdrop-blur dark:border-slate-800 dark:bg-slate-950/90 sm:px-6 sm:py-4 lg:px-8">
    <div class="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
        <div>
            <p class="text-xs font-medium text-slate-500 dark:text-slate-400 sm:text-sm">Live production data from HighLevel</p>
            <h2 class="mt-1 text-xl font-semibold tracking-normal text-slate-950 dark:text-white sm:text-2xl">Campaign workspace</h2>
        </div>

        <div class="flex items-center gap-3 overflow-x-auto pb-1 [-ms-overflow-style:none] [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
            <nav data-filter-nav class="filter-nav flex shrink-0 items-center gap-1" aria-label="Quick filters">
                @foreach ($filterOptions as $option)
                    <button type="button" data-filter-button="{{ $option['filter'] }}" class="shrink-0 px-2.5 py-2 text-xs font-medium text-slate-500 transition-colors hover:text-slate-950 data-[active=true]:text-slate-950 dark:text-slate-400 dark:hover:text-white dark:data-[active=true]:text-white sm:px-3 sm:text-sm" @if ($option['filter'] === 'all') data-active="true" @endif>
                        {{ $option['label'] }}
                    </button>
                @endforeach
                <span data-filter-indicator class="filter-nav-indicator" aria-hidden="true"></span>
            </nav>

            <button type="button" data-theme-toggle aria-label="Toggle theme" title="Toggle theme" class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-950 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white">
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
