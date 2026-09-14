<section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_420px]">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($groups as $groupName => $segments)
            @php
                $groupKey = str($groupName)->lower()->replace([',', ' '], ['', '-']);
                $groupTotal = collect($segments)->sum('total');
            @endphp

            <article data-segment-section data-filter-key="{{ $groupKey }}" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950 dark:text-white">{{ $groupName }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ number_format($groupTotal) }} contacts</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ count($segments) }} segments</span>
                </div>

                <div class="mt-4 h-2 rounded-full bg-slate-100 dark:bg-slate-800">
                    <div class="h-2 rounded-full bg-teal-600" style="width: {{ max(round(($groupTotal / $maxGroupTotal) * 100), 2) }}%"></div>
                </div>

                <div class="mt-4 grid gap-2">
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
            <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Priority segments</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Largest queues first.</p>
            </div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($prioritySegments as $segment)
                    <button type="button" data-filter-button="{{ $filterKey($segment['group'], $segment['tag']) }}" class="grid w-full gap-1 px-4 py-4 text-left hover:bg-slate-50 dark:hover:bg-slate-950/70">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-200">{{ $segment['label'] }}</span>
                            <span class="text-sm font-semibold text-slate-950 dark:text-white">{{ number_format($segment['total']) }}</span>
                        </div>
                        <span class="truncate text-xs text-slate-400 dark:text-slate-500">{{ $segment['group'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-950 dark:text-white">Date window</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Refine the visible company samples.</p>
                </div>
                @if ($dateRange['from'] || $dateRange['to'])
                    <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Clear</a>
                @endif
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="mt-4 grid gap-3">
                <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
                    <label class="grid gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        From
                        <input type="date" name="from" value="{{ $dateRange['from'] }}" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-teal-400 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:[color-scheme:dark]">
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        To
                        <input type="date" name="to" value="{{ $dateRange['to'] }}" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-teal-400 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:[color-scheme:dark]">
                    </label>
                </div>
                <button type="submit" class="h-10 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">Apply range</button>
            </form>
        </section>

    </aside>
</section>
