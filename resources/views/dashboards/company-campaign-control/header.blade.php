<header class="sticky top-0 z-10 border-b border-slate-200 bg-white/90 px-4 py-4 backdrop-blur sm:px-6 lg:px-8">
    <div class="flex flex-col gap-4 2xl:flex-row 2xl:items-center 2xl:justify-between">
        <div>
            <p class="text-sm font-medium text-slate-500">Live production data from HighLevel</p>
            <h2 class="mt-1 text-2xl font-semibold tracking-normal text-slate-950">Campaign workspace</h2>
        </div>

        <div class="flex max-w-full gap-2 overflow-x-auto pb-1" aria-label="Quick filters">
            @foreach ($filterOptions as $option)
                <button type="button" data-filter-button="{{ $option['filter'] }}" class="shrink-0 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 shadow-sm hover:border-teal-300 hover:text-teal-800 data-[active=true]:border-slate-950 data-[active=true]:bg-slate-950 data-[active=true]:text-white" @if ($option['filter'] === 'all') data-active="true" @endif>
                    {{ $option['label'] }}
                </button>
            @endforeach
        </div>
    </div>
</header>
