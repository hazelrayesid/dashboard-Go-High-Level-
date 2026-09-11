<section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
    <div class="rounded-md border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700">Current queue</p>
                <p class="mt-2 text-5xl font-semibold tracking-normal text-slate-950">{{ number_format($remainingTotal) }}</p>
                <p class="mt-2 text-sm leading-6 text-slate-500">Remaining contacts across Top4 signup and Crazy Domains lists.</p>
            </div>

            <div class="grid min-w-64 gap-3 sm:grid-cols-3 lg:grid-cols-1">
                <button type="button" data-filter-button="has-website" class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-left hover:border-teal-300 hover:bg-white">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500">Sent</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($sentTotal) }}</p>
                </button>
                <button type="button" data-filter-button="report-generated" class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-left hover:border-teal-300 hover:bg-white">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500">Report generated</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($reportTotal) }}</p>
                </button>
                <button type="button" data-filter-button="all" class="rounded-md border border-slate-200 bg-slate-50 px-4 py-3 text-left hover:border-teal-300 hover:bg-white">
                    <p class="text-xs font-medium uppercase tracking-normal text-slate-500">Companies loaded</p>
                    <p class="mt-1 text-2xl font-semibold text-slate-950">{{ number_format($totals['companies_loaded']) }}</p>
                </button>
            </div>
        </div>
    </div>

    <div class="rounded-md border border-slate-900 bg-slate-950 p-5 text-white shadow-sm">
        <p class="text-sm font-medium text-teal-200">Report progress</p>
        <div class="mt-5 flex items-end justify-between gap-4">
            <p class="text-5xl font-semibold tracking-normal">{{ $reportRate }}%</p>
            <p class="max-w-48 text-sm leading-6 text-slate-300">{{ number_format($reportTotal) }} reports from {{ number_format($sentTotal) }} sent contacts.</p>
        </div>
        <div class="mt-6 h-2 rounded-full bg-white/10">
            <div class="h-2 rounded-full bg-teal-300" style="width: {{ max(min($reportRate, 100), 2) }}%"></div>
        </div>
    </div>
</section>
