<section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_420px]">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($groups as $groupName => $segments)
            @php
                $groupKey = str($groupName)->lower()->replace([',', ' '], ['', '-']);
                $groupTotal = collect($segments)->sum('total');
            @endphp

            <article data-segment-section data-filter-key="{{ $groupKey }}" class="rounded-md border border-slate-200 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-4">
                <div class="flex items-start justify-between gap-3 sm:gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $groupName }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ number_format($groupTotal) }} contacts</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ count($segments) }} segments</span>
                </div>

                <div class="mt-3 h-2 rounded-full bg-slate-100 dark:bg-slate-800 sm:mt-4">
                    <div class="h-2 rounded-full bg-teal-600" style="width: {{ max(round(($groupTotal / $maxGroupTotal) * 100), 2) }}%"></div>
                </div>

                <div class="mt-3 grid gap-2 sm:mt-4">
                    @foreach ($segments as $segment)
                        <button type="button" data-filter-button="{{ $filterKey($groupName, $segment['tag']) }}" class="rounded-md border border-slate-100 bg-slate-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white dark:border-slate-800 dark:bg-slate-950/60 dark:hover:border-teal-500 dark:hover:bg-slate-950">
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $segment['label'] }}</span>
                                <span class="text-sm font-semibold text-slate-950 dark:text-white">{{ number_format($segment['total']) }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-400 dark:text-slate-500">{{ $segment['tag'] }}</p>
                        </button>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>

    <aside class="grid content-start gap-4">
        <section class="rounded-md border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-100 px-3 py-3 dark:border-slate-800 sm:px-4 sm:py-4">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Priority segments</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Largest queues first.</p>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($prioritySegments as $segment)
                    <button type="button" data-filter-button="{{ $filterKey($segment['group'], $segment['tag']) }}" class="grid w-full gap-1 px-3 py-3 text-left hover:bg-slate-50 dark:hover:bg-slate-950/70 sm:px-4 sm:py-4">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $segment['label'] }}</span>
                            <span class="text-sm font-semibold text-slate-950 dark:text-white">{{ number_format($segment['total']) }}</span>
                        </div>
                        <span class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $segment['group'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

    </aside>
</section>
