<section class="rounded-md border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-2 border-b border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-base font-semibold text-slate-950">Company queue</h3>
            <p class="mt-1 text-sm text-slate-500">
                Latest company samples{{ $dateRange['from'] || $dateRange['to'] ? ' inside the selected date range.' : ' from the active campaign view.' }}
            </p>
        </div>
        <span class="text-sm text-slate-500" data-visible-count>{{ $visibleCompanyCount }} visible records</span>
    </div>

    <div class="grid gap-4 p-4">
        @foreach ($companyQueue as $segment)
            <article data-queue-section data-filter-key="{{ $segment['filter_key'] }}" class="rounded-md border border-slate-200 bg-slate-50">
                <div class="flex flex-col gap-3 border-b border-slate-200 bg-white px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <h4 class="text-sm font-semibold text-slate-950">{{ $segment['group'] }}</h4>
                        <p class="mt-1 text-xs font-medium text-slate-500">{{ $segment['label'] }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-600">{{ number_format($segment['total']) }}</span>
                        <span class="text-xs text-slate-400">{{ count($segment['companies']) }} sample</span>
                    </div>
                </div>

                @if (count($segment['companies']) > 0)
                    <div class="grid gap-3 p-3 md:grid-cols-2 2xl:grid-cols-3">
                        @foreach ($segment['companies'] as $company)
                            @php
                                $businessValue = $company['business'];
                                $websiteUrl = str_starts_with($businessValue, 'http') ? $businessValue : 'https://'.$businessValue;
                                $createdDate = $company['created_date'] ?? '';
                            @endphp

                            <article data-company-row data-created-date="{{ $createdDate }}" data-filter-key="{{ $segment['filter_key'] }}" class="rounded-md border border-slate-200 bg-white p-4 shadow-sm hover:border-teal-300">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <h5 class="truncate text-base font-semibold text-slate-950">{{ $company['business'] }}</h5>
                                        <p class="mt-1 truncate text-sm text-slate-500">{{ $company['name'] }}</p>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-2 text-sm text-slate-600">
                                    <p class="truncate">{{ $company['email'] }}</p>
                                    @if ($company['phone'] !== '-')
                                        <p class="truncate">{{ $company['phone'] }}</p>
                                    @endif
                                    @if ($createdDate)
                                        <p class="text-xs text-slate-400">Created {{ $createdDate }}</p>
                                    @endif
                                </div>

                                <div class="mt-4 flex items-center gap-2">
                                    <a href="{{ $websiteUrl }}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:border-teal-300 hover:text-teal-800">Website</a>
                                    <button type="button" data-copy-value="{{ $company['email'] }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:border-teal-300 hover:text-teal-800">Copy email</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <div class="px-4 py-5 text-sm text-slate-500">
                        No displayable company samples returned for this segment.
                    </div>
                @endif
            </article>
        @endforeach

        <div data-empty-queue class="hidden rounded-md border border-dashed border-slate-300 p-10 text-center">
            <h4 class="text-sm font-semibold text-slate-950">No company rows match this filter</h4>
            <p class="mt-2 text-sm text-slate-500">Switch filters or refresh to load the latest HighLevel sample.</p>
        </div>
    </div>
</section>
