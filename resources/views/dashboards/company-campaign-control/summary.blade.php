@php
    $calendarMonthEmailMatches = $googleCalendar['calendar_month_email_matches'] ?? [
        'available' => true,
        'emails_count' => 0,
        'events_count' => 0,
        'items' => [],
    ];
    $calendarMonthEmailItems = collect($calendarMonthEmailMatches['items'] ?? []);
    $calendarMonthLabel = $googleCalendar['calendar_month_label'] ?? now()->format('F Y');
@endphp

<section class="grid gap-4 xl:grid-cols-[minmax(0,1.35fr)_minmax(360px,0.65fr)]">
    <div class="rounded-md border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Current queue</p>
                <p class="mt-1 text-4xl font-semibold tracking-normal text-slate-950 dark:text-white sm:mt-2 sm:text-5xl">{{ number_format($remainingTotal) }}</p>
                <p class="mt-2 text-sm leading-5 text-slate-500 dark:text-slate-400 sm:leading-6">Remaining contacts across Top4 signup and Crazy Domains lists.</p>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:min-w-[32rem] lg:grid-cols-2 lg:gap-3">
                <button type="button" data-email-match-open class="min-w-0 rounded-md border border-teal-200 bg-teal-50 px-3 py-2 text-left hover:border-teal-300 hover:bg-white dark:border-teal-500/30 dark:bg-teal-500/10 dark:hover:border-teal-400 dark:hover:bg-teal-500/15 sm:px-4 sm:py-3">
                    <p class="truncate text-xs font-medium uppercase tracking-normal text-teal-800 dark:text-teal-200">Email Calendar Match GHL</p>
                    <div class="mt-1 flex items-end justify-between gap-3">
                        <p class="text-lg font-semibold text-slate-950 dark:text-white sm:text-2xl">{{ number_format($calendarMonthEmailMatches['emails_count'] ?? 0) }}</p>
                        <p class="truncate pb-0.5 text-[11px] font-medium text-teal-700/80 dark:text-teal-200/80">{{ $calendarMonthLabel }}</p>
                    </div>
                </button>
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

    <dialog data-email-match-dialog class="fixed inset-0 m-auto max-h-[calc(100vh-2rem)] w-[min(760px,calc(100vw-2rem))] overflow-hidden rounded-md border border-slate-200 bg-white p-0 text-slate-950 shadow-2xl backdrop:bg-slate-950/60 dark:border-slate-800 dark:bg-slate-900 dark:text-white">
        <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-4 py-4 dark:border-slate-800 sm:px-5">
            <div>
                <p class="text-base font-semibold">Email Calendar Match GHL This Month</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $calendarMonthLabel }} &middot; {{ number_format($calendarMonthEmailMatches['emails_count'] ?? 0) }} matched emails &middot; {{ number_format($calendarMonthEmailMatches['events_count'] ?? 0) }} calendar event matches
                </p>
            </div>
            <button type="button" data-email-match-close class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Close</button>
        </div>

        <div class="max-h-[70vh] overflow-y-auto p-4 sm:p-5">
            @if (! ($calendarMonthEmailMatches['available'] ?? true))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200">
                    GHL email matching is temporarily unavailable.
                </div>
            @elseif ($calendarMonthEmailItems->isEmpty())
                <div class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center dark:border-slate-700 dark:bg-slate-950/60">
                    <p class="text-sm font-semibold text-slate-950 dark:text-white">No matched calendar emails this month</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Calendar attendees did not match synced GHL contacts for this month.</p>
                </div>
            @else
                <div class="grid gap-3">
                    @foreach ($calendarMonthEmailItems as $item)
                        <article class="rounded-md border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950/60 sm:p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $item['email'] }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                        {{ number_format($item['events_count'] ?? 0) }} event match{{ ($item['events_count'] ?? 0) === 1 ? '' : 'es' }}
                                        &middot; {{ number_format($item['match_count'] ?? 1) }} GHL contact{{ ($item['match_count'] ?? 1) === 1 ? '' : 's' }}
                                    </p>
                                </div>
                                @if (! empty($item['businesses']))
                                    <div class="flex flex-wrap gap-1.5 sm:justify-end">
                                        @foreach (collect($item['businesses'])->take(4) as $business)
                                            <span class="max-w-full truncate rounded-md border border-teal-200 bg-teal-50 px-2 py-1 text-[11px] font-medium text-teal-800 dark:border-teal-500/30 dark:bg-teal-500/10 dark:text-teal-200">{{ $business }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="mt-3 grid gap-2">
                                @foreach (collect($item['events'] ?? [])->take(5) as $event)
                                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="truncate text-xs font-semibold text-slate-950 dark:text-white">{{ $event['title'] ?? 'Calendar event' }}</p>
                                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event['date'] ?? '' }} &middot; {{ $event['time'] ?? '' }} &middot; {{ $event['calendar'] ?? 'Calendar' }}</p>
                                            </div>
                                            <div class="flex shrink-0 items-center gap-2">
                                                @if (! empty($event['meeting_link']))
                                                    <a href="{{ $event['meeting_link'] }}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Meet</a>
                                                @endif
                                                @if (! empty($event['link']))
                                                    <a href="{{ $event['link'] }}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Open</a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </dialog>
</section>
