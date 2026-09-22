<section data-email-performance-card class="rounded-md border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <script type="application/json" data-email-metric-data>
        {!! json_encode([
            'active' => $activeMetricKey,
            'metrics' => collect($metricOptions)->map(fn (array $option): array => [
                'label' => $option['label'],
                'unit' => $option['unit'],
                'value' => $option['value'],
                'point_key' => $option['point_key'],
                'tick_min' => $option['tick_min'],
                'rows' => $option['rows'],
            ])->all(),
            'points' => $emailPoints->values()->all(),
            'stats_count' => $emailStats['stats_count'] ?? 0,
            'chart' => [
                'plot_top' => $plotTop,
                'plot_bottom' => $plotBottom,
                'plot_left' => $plotLeft,
                'plot_right' => $plotRight,
                'plot_height' => $plotHeight,
            ],
        ]) !!}
    </script>
    <div class="border-b border-slate-100 px-4 py-4 dark:border-slate-800 sm:px-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Performance Analysis</h3>
                <p class="mt-3 text-sm text-slate-600 dark:text-slate-400">Track campaign performance trends for a metric over time.</p>
                @if (session('ghl_email_workflows_status'))
                    <p class="mt-2 text-xs font-medium text-teal-700 dark:text-teal-300">{{ session('ghl_email_workflows_status') }}</p>
                @endif
            </div>

            <div class="grid gap-2 lg:justify-items-end">
                <form method="GET" action="{{ route('dashboard') }}" class="grid gap-2 sm:grid-cols-[150px_150px_auto] sm:items-end">
                    @foreach ($queryWithoutEmailRange as $key => $value)
                        @if (is_scalar($value))
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endif
                    @endforeach
                    <label class="grid gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        From
                        <span data-date-shell class="relative block">
                            <button type="button" data-date-button class="flex h-10 w-full items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 text-left text-sm font-medium text-slate-700 shadow-sm outline-none hover:border-teal-300 focus:border-teal-400 focus:ring-2 focus:ring-teal-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-teal-500 dark:focus:ring-teal-500/10">
                                <span data-date-label>{{ \Carbon\CarbonImmutable::parse($emailStats['range']['from'] ?? now()->toDateString())->format('m/d/Y') }}</span>
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400">
                                    <path d="M6 2.5v3M14 2.5v3M3.5 8h13M5.5 4h9A2.5 2.5 0 0 1 17 6.5v8A2.5 2.5 0 0 1 14.5 17h-9A2.5 2.5 0 0 1 3 14.5v-8A2.5 2.5 0 0 1 5.5 4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <input data-date-input type="date" name="email_from" value="{{ $emailStats['range']['from'] ?? '' }}" class="pointer-events-none absolute inset-0 h-10 w-full opacity-0 dark:[color-scheme:dark]" tabindex="-1" aria-hidden="true">
                        </span>
                    </label>
                    <label class="grid gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                        To
                        <span data-date-shell class="relative block">
                            <button type="button" data-date-button class="flex h-10 w-full items-center justify-between gap-3 rounded-md border border-slate-200 bg-white px-3 text-left text-sm font-medium text-slate-700 shadow-sm outline-none hover:border-teal-300 focus:border-teal-400 focus:ring-2 focus:ring-teal-100 dark:border-slate-800 dark:bg-slate-950 dark:text-slate-200 dark:hover:border-teal-500 dark:focus:ring-teal-500/10">
                                <span data-date-label>{{ \Carbon\CarbonImmutable::parse($emailStats['range']['to'] ?? now()->toDateString())->format('m/d/Y') }}</span>
                                <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" class="h-4 w-4 shrink-0 text-slate-500 dark:text-slate-400">
                                    <path d="M6 2.5v3M14 2.5v3M3.5 8h13M5.5 4h9A2.5 2.5 0 0 1 17 6.5v8A2.5 2.5 0 0 1 14.5 17h-9A2.5 2.5 0 0 1 3 14.5v-8A2.5 2.5 0 0 1 5.5 4Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                            <input data-date-input type="date" name="email_to" value="{{ $emailStats['range']['to'] ?? '' }}" class="pointer-events-none absolute inset-0 h-10 w-full opacity-0 dark:[color-scheme:dark]" tabindex="-1" aria-hidden="true">
                        </span>
                    </label>
                    <button type="submit" class="h-10 rounded-md bg-slate-950 px-4 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">Apply range</button>
                </form>

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
                                @if (is_scalar($value))
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
            <h4 data-email-metric-title class="text-base font-semibold text-slate-950 dark:text-white">{{ $activeMetric['label'] }} (for All Campaigns)</h4>
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
                        <a href="{{ $metricUrl }}" data-email-metric-option="{{ $metricKey }}" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left {{ $isActiveMetric ? 'bg-slate-50 font-medium text-blue-600 dark:bg-slate-900 dark:text-blue-300' : 'hover:bg-slate-50 dark:hover:bg-slate-900' }}">
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
                    <p data-email-metric-label class="text-sm font-medium text-slate-600 dark:text-slate-400">{{ $activeMetric['label'] }}</p>
                    <p data-email-metric-value class="mt-1 text-4xl font-semibold tracking-normal text-slate-950 dark:text-white">
                        {{ $isRateMetric ? number_format((float) $activeMetric['value'], 2) : number_format((int) $activeMetric['value']) }}{{ $activeMetric['unit'] }}
                    </p>
                </div>
                <div data-email-metric-rows class="grid gap-3 border-t border-slate-100 px-4 py-6 text-sm dark:border-slate-800">
                    @foreach ($activeMetric['rows'] as $rowLabel => $rowValue)
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-slate-600 dark:text-slate-400">{{ $rowLabel }}</span>
                            <span class="font-medium text-slate-800 dark:text-slate-200">{{ number_format($rowValue) }}</span>
                        </div>
                    @endforeach
                    <p class="text-xs text-slate-400 dark:text-slate-500">{{ number_format($emailStats['stats_count'] ?? 0) }} campaign stats loaded</p>
                </div>
            </aside>

            <div data-email-chart-wrap class="relative min-w-0">
                <div class="overflow-x-auto">
                    <svg data-email-chart viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" role="img" aria-label="{{ $activeMetric['label'] }} chart" class="h-[270px] min-w-[620px] w-full">
                        @foreach ($chartTicks as $tick)
                            @php
                                $tickY = $plotBottom - ($plotHeight * ($tick / $tickMax));
                            @endphp
                            <line x1="{{ $plotLeft }}" y1="{{ $tickY }}" x2="{{ $plotRight }}" y2="{{ $tickY }}" stroke="currentColor" class="text-slate-100 dark:text-slate-800" stroke-width="1" />
                            <text data-email-y-tick="{{ $loop->index }}" x="8" y="{{ $tickY + 4 }}" class="fill-slate-500 text-[12px] dark:fill-slate-400">{{ number_format($tick) }}{{ $activeMetric['unit'] }}</text>
                        @endforeach

                        @if ($areaPath)
                            <path data-email-area d="{{ $areaPath }}" fill="#e8e7f4" class="dark:fill-slate-800" opacity="0.9" />
                            <path data-email-line d="{{ $linePath }}" fill="none" stroke="#687291" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
                        @endif
                        <g data-email-points>
                            @foreach ($pathPoints as $index => $point)
                                <circle data-email-point="{{ $index }}" cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="4" fill="#687291" stroke="white" stroke-width="2" class="transition-all duration-150 dark:stroke-slate-950" />
                            @endforeach
                        </g>

                        <line x1="{{ $plotLeft }}" y1="{{ $plotBottom }}" x2="{{ $plotRight }}" y2="{{ $plotBottom }}" stroke="#2dd4bf" stroke-width="2" />
                        <text data-email-axis-label x="4" y="106" transform="rotate(-90 4 106)" class="fill-slate-500 text-[12px] dark:fill-slate-400">{{ $activeMetric['label'] }}</text>

                        @foreach ($emailPoints->values() as $index => $point)
                            @php
                                $x = $pointCount === 1 ? ($plotLeft + $plotRight) / 2 : $plotLeft + (($plotRight - $plotLeft) * ($index / ($pointCount - 1)));
                            @endphp
                            <text x="{{ $x }}" y="206" text-anchor="middle" class="fill-slate-500 text-[12px] dark:fill-slate-400">{{ $point['label'] }}</text>
                        @endforeach
                        <text x="442" y="220" text-anchor="middle" class="fill-slate-500 text-[12px] dark:fill-slate-400">Dates</text>
                    </svg>
                </div>
                <div data-email-chart-tooltip class="pointer-events-none absolute z-30 hidden max-w-xs rounded-md bg-slate-950 px-3 py-2 text-xs text-white shadow-xl ring-1 ring-black/10 dark:bg-slate-950"></div>

                <div class="mt-1 flex flex-wrap items-center justify-center gap-x-5 gap-y-2 text-xs text-slate-600 dark:text-slate-400">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-[#687291]"></span>All Campaigns</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-blue-500"></span>Email Campaign</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-violet-400"></span>Workflow Campaign</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-sky-400"></span>Bulk Action Campaign</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 bg-teal-500"></span>Email sequences</span>
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
