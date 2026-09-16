<section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
    <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Current queue</p>
                <p class="mt-1 text-4xl font-semibold tracking-normal text-slate-950 dark:text-white sm:mt-2 sm:text-5xl">{{ number_format($remainingTotal) }}</p>
                <p class="mt-2 text-sm leading-5 text-slate-500 dark:text-slate-400 sm:leading-6">Remaining contacts across Top4 signup and Crazy Domains lists.</p>
            </div>

            <div class="grid gap-2 sm:grid-cols-3 lg:min-w-64 lg:grid-cols-1 lg:gap-3">
                <button type="button" data-filter-button="has-website" class="min-w-0 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-teal-500 dark:hover:bg-slate-950 sm:px-4 sm:py-3">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500 dark:text-slate-400">Sent</p>
                    <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-white sm:text-2xl">{{ number_format($sentTotal) }}</p>
                </button>
                <button type="button" data-filter-button="report-generated" class="min-w-0 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-teal-500 dark:hover:bg-slate-950 sm:px-4 sm:py-3">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500 dark:text-slate-400">Report generated</p>
                    <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-white sm:text-2xl">{{ number_format($reportTotal) }}</p>
                </button>
                <button type="button" data-filter-button="all" class="min-w-0 rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-teal-500 dark:hover:bg-slate-950 sm:px-4 sm:py-3">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500 dark:text-slate-400">Companies loaded</p>
                    <p class="mt-1 text-lg font-semibold text-slate-950 dark:text-white sm:text-2xl">{{ number_format($totals['companies_loaded']) }}</p>
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-md border border-slate-900 bg-slate-950 p-4 text-white shadow-sm dark:border-slate-800 dark:bg-black sm:p-5">
        <p class="text-sm font-medium text-teal-200">Report progress</p>
        <div class="mt-4 flex flex-col gap-2 sm:mt-5 sm:flex-row sm:items-end sm:justify-between sm:gap-4">
            <p class="text-4xl font-semibold tracking-normal sm:text-5xl">{{ $reportRate }}%</p>
            <p class="max-w-44 text-sm leading-5 text-slate-300 sm:max-w-48 sm:leading-6">{{ number_format($reportTotal) }} reports from {{ number_format($sentTotal) }} sent contacts.</p>
        </div>
        <div class="mt-4 h-2 rounded-full bg-white/10 sm:mt-6">
            <div class="h-2 rounded-full bg-teal-300" style="width: {{ max(min($reportRate, 100), 2) }}%"></div>
        </div>
    </div>
</section>
