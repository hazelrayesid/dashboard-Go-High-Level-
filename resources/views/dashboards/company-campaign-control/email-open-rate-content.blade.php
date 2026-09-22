<section data-email-performance-card class="rounded-md border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Performance Analysis</h3>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Selected workflow campaign performance from HighLevel campaign stats.</p>
                @if (session('ghl_email_workflows_status'))
                    <p class="mt-2 text-xs font-medium text-teal-700 dark:text-teal-300">{{ session('ghl_email_workflows_status') }}</p>
                @endif
            </div>

            <div class="grid gap-2 lg:justify-items-end">
                <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600 dark:border-slate-800 dark:bg-slate-950/60 dark:text-slate-400">
                    Snapshot summary from selected workflows. Refreshed from HighLevel every minute.
                </div>

                @if ($workflowOptions->isNotEmpty())
                    <details class="group relative w-full sm:w-[460px]">
                        <summary class="flex h-10 cursor-pointer list-none items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm outline-none hover:border-slate-300 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:focus:ring-blue-500/10">
                            <span>{{ number_format($selectedWorkflowCount) }} workflow campaign{{ $selectedWorkflowCount === 1 ? '' : 's' }} selected</span>
                            <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="h-4 w-4 text-slate-400 dark:text-slate-500">
                                <path d="M5.5 7.5L10 12l4.5-4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </summary>
                        <form method="POST" action="{{ route('ghl.email-workflows.update') }}" class="absolute right-0 z-30 mt-1 w-full overflow-hidden rounded-md border border-slate-200 bg-white text-sm text-slate-700 shadow-xl dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                            @csrf
                            @foreach (request()->query() as $key => $value)
                                @if (is_scalar($value) && ! in_array($key, ['email_from', 'email_to'], true))
                                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                                @endif
                            @endforeach
                            <div class="max-h-80 overflow-y-auto p-2">
                                @foreach ($workflowOptions as $workflow)
                                    <label class="flex cursor-pointer items-start gap-3 rounded-md px-2 py-2 hover:bg-slate-50 dark:hover:bg-slate-900">
                                        <input type="checkbox" name="workflow_ids[]" value="{{ $workflow['id'] }}" @checked($workflow['enabled']) class="mt-1 rounded border-slate-300 text-teal-600 focus:ring-teal-500 dark:border-slate-700 dark:bg-slate-900">
                                        <span class="min-w-0">
                                            <span class="block truncate font-medium text-slate-800 dark:text-slate-100">{{ $workflow['name'] }}</span>
                                            <span class="mt-1 inline-flex rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-500 dark:bg-slate-900 dark:text-slate-400">{{ $workflow['status'] ?: 'unknown' }}</span>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            <div class="flex items-center justify-between gap-3 border-t border-slate-100 px-3 py-3 dark:border-slate-800">
                                <span class="text-xs text-slate-400 dark:text-slate-500">{{ number_format($workflowOptions->count()) }} workflows loaded</span>
                                <button type="submit" class="rounded-md bg-slate-950 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">Save selection</button>
                            </div>
                        </form>
                    </details>
                @endif
            </div>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ([
                'Email Delivered' => $emailTotals['delivered'] ?? 0,
                'Bounced' => $emailTotals['bounced'] ?? 0,
                'Unsubscribed' => $emailTotals['unsubscribed'] ?? 0,
                'Spam Complaints' => $emailTotals['spam_complaints'] ?? 0,
            ] as $label => $value)
                <div class="rounded-md border border-slate-200 bg-white px-4 py-4 shadow-sm dark:border-slate-800 dark:bg-slate-950/50">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-semibold tracking-normal text-slate-950 dark:text-white">{{ number_format($value) }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="p-4 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h4 class="text-base font-semibold text-slate-950 dark:text-white">{{ $activeMetric['label'] }} by selected workflow</h4>
            <details class="group relative sm:min-w-48">
                <summary class="flex h-10 cursor-pointer list-none items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-700 shadow-sm outline-none hover:border-slate-300 focus:border-blue-400 focus:ring-4 focus:ring-blue-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-slate-700 dark:focus:ring-blue-500/10">
                    <span data-email-metric-current>{{ $activeMetric['label'] }}</span>
                    <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="h-4 w-4 text-slate-400 dark:text-slate-500">
                        <path d="M5.5 7.5L10 12l4.5-4.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </summary>
                <div class="absolute right-0 z-20 mt-1 w-48 overflow-hidden rounded-md border border-slate-200 bg-white py-1 text-sm text-slate-700 shadow-xl dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200">
                    @foreach ($metricOptions as $metricKey => $metricOption)
                        @php
                            $metricUrl = route('dashboard', array_merge($metricBaseQuery, ['email_metric' => $metricKey]));
                            $isActiveMetric = $activeMetricKey === $metricKey;
                        @endphp
                        <a href="{{ $metricUrl }}" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left {{ $isActiveMetric ? 'bg-slate-50 font-medium text-blue-600 dark:bg-slate-900 dark:text-blue-300' : 'hover:bg-slate-50 dark:hover:bg-slate-900' }}">
                            <span>{{ $metricOption['label'] }}</span>
                            <svg data-email-metric-check aria-hidden="true" viewBox="0 0 20 20" fill="none" class="h-4 w-4 {{ $isActiveMetric ? '' : 'hidden' }}">
                                <path d="M15.5 5.75 8.25 13 4.5 9.25" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endforeach
                </div>
            </details>
        </div>

        @if (! ($emailStats['available'] ?? true))
            <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                {{ $emailStats['error'] ?? 'GHL email statistics are temporarily unavailable.' }}
            </div>
        @endif

        <div class="mt-5 grid gap-4 lg:grid-cols-[224px_minmax(0,1fr)] lg:items-stretch">
            <aside class="rounded-md border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-950/50">
                <div class="px-4 py-8">
                    <p class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $activeMetric['label'] }}</p>
                    <p class="mt-1 text-4xl font-semibold tracking-normal text-slate-950 dark:text-white">
                        {{ $isRateMetric ? number_format((float) $activeMetric['value'], 2) : number_format((int) $activeMetric['value']) }}{{ $activeMetric['unit'] }}
                    </p>
                </div>
                <div class="grid gap-3 border-t border-slate-100 px-4 py-6 text-sm dark:border-slate-800">
                    @foreach ($activeMetric['rows'] as $rowLabel => $rowValue)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-600 dark:text-slate-400">{{ $rowLabel }}</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ number_format($rowValue) }}</span>
                        </div>
                    @endforeach
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ number_format($emailStats['stats_count'] ?? 0) }} campaign stats loaded</p>
                </div>
            </aside>

            <div class="min-w-0 rounded-md border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950/40">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <p class="text-sm font-semibold text-slate-950 dark:text-white">Workflow comparison</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">This is a campaign summary comparison, not a daily trend.</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-slate-400 dark:text-slate-500">{{ number_format($chartRows->count()) }} workflows</span>
                </div>

                <div class="mt-4 grid gap-3">
                    @forelse ($chartRows as $item)
                        @php
                            $metricValue = (float) ($item[$activeMetric['breakdown_key']] ?? 0);
                            $barWidth = $chartMax > 0 ? min(100, max(1, ($metricValue / $chartMax) * 100)) : 0;
                        @endphp
                        <div class="grid gap-2 sm:grid-cols-[minmax(180px,260px)_minmax(0,1fr)_90px] sm:items-center">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-slate-800 dark:text-slate-100" title="{{ $item['campaign_name'] }}">{{ $item['campaign_name'] }}</p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ number_format($item['opened']) }} opened / {{ number_format($item['delivered']) }} delivered</p>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-[#687291]" style="width: {{ $barWidth }}%"></div>
                            </div>
                            <p class="text-right text-sm font-semibold text-slate-950 dark:text-white">
                                {{ $isRateMetric ? number_format($metricValue, 2) : number_format((int) $metricValue) }}{{ $activeMetric['unit'] }}
                            </p>
                        </div>
                    @empty
                        <div class="rounded-md border border-dashed border-slate-200 px-4 py-8 text-center text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                            No workflow stats loaded yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        @if ($emailBreakdown->isNotEmpty())
            <div data-email-breakdown class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h5 class="text-sm font-semibold text-slate-950 dark:text-white">Campaign breakdown</h5>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Workflow campaign engagement summary from HighLevel campaign stats.</p>
                    </div>
                    <span data-email-breakdown-count class="text-xs font-medium text-slate-400 dark:text-slate-500">{{ number_format($emailBreakdown->count()) }} email action{{ $emailBreakdown->count() === 1 ? '' : 's' }}</span>
                </div>

                <div class="mt-3 overflow-hidden rounded-md border border-slate-200 dark:border-slate-800">
                    @foreach ($emailBreakdown as $item)
                        <article data-email-breakdown-row class="grid gap-4 border-b border-slate-100 bg-white px-4 py-4 last:border-b-0 dark:border-slate-800 dark:bg-slate-950/40 xl:grid-cols-[minmax(240px,1fr)_minmax(0,2fr)] xl:items-center">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="rounded-md bg-violet-50 px-2 py-1 text-[11px] font-semibold text-violet-700 dark:bg-violet-500/10 dark:text-violet-200">{{ $item['source_label'] }}</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">Open rate {{ number_format((float) $item['open_rate'], 2) }}%</span>
                                </div>
                                <p class="mt-2 truncate text-sm font-semibold text-slate-950 dark:text-white" title="{{ $item['campaign_name'] }}">{{ $item['campaign_name'] }}</p>
                                @if (! empty($item['email_name']))
                                    <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $item['email_name'] }}">{{ $item['email_name'] }}</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 xl:grid-cols-7">
                                @foreach ([
                                    'Delivered' => $item['delivered'],
                                    'Opened' => $item['opened'],
                                    'Clicked' => $item['clicked'],
                                    'Soft Bounced' => $item['soft_bounced'],
                                    'Hard Bounced' => $item['hard_bounced'],
                                    'Unsubscribed' => $item['unsubscribed'],
                                    'Spam' => $item['spam_complaints'],
                                ] as $label => $value)
                                    <div class="min-w-0 rounded-md border border-slate-100 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
                                        <p class="truncate text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ $label }}</p>
                                        <p class="mt-1 text-lg font-semibold tracking-normal text-slate-950 dark:text-white">{{ number_format($value) }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>

                <div data-email-breakdown-pagination class="hidden flex-col gap-3 pt-3 sm:flex-row sm:items-center sm:justify-between">
                    <p data-email-breakdown-pagination-label class="text-xs text-slate-500 dark:text-slate-400">Page 1 of 1 - 5 per page</p>
                    <div class="flex items-center gap-2">
                        <button type="button" data-email-breakdown-prev class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 disabled:border-slate-100 disabled:text-slate-300 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300 dark:disabled:text-slate-600">Previous</button>
                        <button type="button" data-email-breakdown-next class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 disabled:border-slate-100 disabled:text-slate-300 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300 dark:disabled:text-slate-600">Next page</button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</section>
