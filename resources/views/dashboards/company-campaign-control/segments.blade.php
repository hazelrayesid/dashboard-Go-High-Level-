<section class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_420px]">
    <div class="grid gap-4 lg:grid-cols-2">
        @foreach ($groups as $groupName => $segments)
            @php
                $groupKey = str($groupName)->lower()->replace([',', ' '], ['', '-']);
                $groupTotal = collect($segments)->sum('total');
            @endphp

            <article data-segment-section data-filter-key="{{ $groupKey }}" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-base font-semibold text-slate-950">{{ $groupName }}</h3>
                        <p class="mt-1 text-sm text-slate-500">{{ number_format($groupTotal) }} contacts</p>
                    </div>
                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ count($segments) }} segments</span>
                </div>

                <div class="mt-4 h-2 rounded-full bg-slate-100">
                    <div class="h-2 rounded-full bg-teal-600" style="width: {{ max(round(($groupTotal / $maxGroupTotal) * 100), 2) }}%"></div>
                </div>

                <div class="mt-4 grid gap-2">
                    @foreach ($segments as $segment)
                        <button type="button" data-filter-button="{{ $filterKey($groupName, $segment['tag']) }}" class="rounded-md border border-slate-100 bg-slate-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white">
                            <div class="flex items-center justify-between gap-3">
                                <span class="truncate text-sm font-medium text-slate-800">{{ $segment['label'] }}</span>
                                <span class="text-sm font-semibold text-slate-950">{{ number_format($segment['total']) }}</span>
                            </div>
                            <p class="mt-1 truncate text-xs text-slate-400">{{ $segment['tag'] }}</p>
                        </button>
                    @endforeach
                </div>
            </article>
        @endforeach
    </div>

    <aside class="grid content-start gap-4">
        <section class="rounded-md border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-4 py-4">
                <h3 class="text-base font-semibold text-slate-950">Priority segments</h3>
                <p class="mt-1 text-sm text-slate-500">Largest queues first.</p>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach ($prioritySegments as $segment)
                    <button type="button" data-filter-button="{{ $filterKey($segment['group'], $segment['tag']) }}" class="grid w-full gap-1 px-4 py-4 text-left hover:bg-slate-50">
                        <div class="flex items-center justify-between gap-3">
                            <span class="truncate text-sm font-medium text-slate-800">{{ $segment['label'] }}</span>
                            <span class="text-sm font-semibold text-slate-950">{{ number_format($segment['total']) }}</span>
                        </div>
                        <span class="truncate text-xs text-slate-400">{{ $segment['group'] }}</span>
                    </button>
                @endforeach
            </div>
        </section>

        <section class="rounded-md border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-base font-semibold text-slate-950">Date window</h3>
                    <p class="mt-1 text-sm text-slate-500">Refine the visible company samples.</p>
                </div>
                @if ($dateRange['from'] || $dateRange['to'])
                    <a href="{{ route('dashboard') }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-600 hover:border-teal-300 hover:text-teal-800">Clear</a>
                @endif
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="mt-4 grid gap-3">
                <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
                    <label class="grid gap-1 text-xs font-medium text-slate-500">
                        From
                        <input type="date" name="from" value="{{ $dateRange['from'] }}" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-teal-400">
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-slate-500">
                        To
                        <input type="date" name="to" value="{{ $dateRange['to'] }}" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-teal-400">
                    </label>
                </div>
                <button type="submit" class="h-10 rounded-md bg-slate-950 px-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">Apply range</button>
            </form>
        </section>
    </aside>
</section>
