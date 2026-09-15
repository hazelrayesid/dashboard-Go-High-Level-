@php
    $calendarEvents = collect($googleCalendar['events'] ?? []);
    $eventDates = collect($googleCalendar['event_dates'] ?? $calendarEvents->pluck('date_key')->all())->flip();
    $calendarMonth = \Illuminate\Support\Carbon::createFromFormat('Y-m', $googleCalendar['calendar_month'] ?? now()->format('Y-m'))->startOfMonth();
    $calendarStart = $calendarMonth->copy()->startOfWeek(\Carbon\CarbonInterface::SUNDAY);
    $calendarDays = collect(range(0, 41))->map(fn (int $offset) => $calendarStart->copy()->addDays($offset));
    $accountName = $googleCalendar['account']['name'] ?? 'Google account';
    $accountEmail = $googleCalendar['account']['email'] ?? null;
    $eventsTotal = $googleCalendar['events_total'] ?? $calendarEvents->count();
    $monthEventsTotal = $googleCalendar['calendar_month_events_total'] ?? $eventsTotal;
    $eventsPage = $googleCalendar['events_page'] ?? 1;
    $eventsLastPage = $googleCalendar['events_last_page'] ?? 1;
    $eventsPerPage = $googleCalendar['events_per_page'] ?? 6;
    $selectedDate = $googleCalendar['calendar_selected_date'] ?? null;
@endphp

<section class="overflow-hidden rounded-md border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
    <div class="grid lg:grid-cols-[minmax(0,1.1fr)_minmax(360px,0.9fr)]">
        <div class="border-b border-slate-200 p-4 dark:border-slate-800 lg:border-b-0 lg:border-r">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-md bg-slate-950 text-sm font-bold text-white dark:bg-white dark:text-slate-950">14</span>
                        <div>
                            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Google Calendar scheduling</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upcoming calendar context before working the company queue.</p>
                        </div>
                    </div>
                </div>
                <span class="w-fit rounded-md px-2.5 py-1.5 text-xs font-semibold {{ $googleCalendar['connected'] ? 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-200' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                    {{ $googleCalendar['connected'] ? 'Connected' : 'Not connected' }}
                </span>
            </div>

            @if (session('google_calendar_status'))
                <p class="mt-4 rounded-md bg-teal-50 px-3 py-2 text-sm font-medium text-teal-800 dark:bg-teal-500/10 dark:text-teal-200">{{ session('google_calendar_status') }}</p>
            @endif

            @if (session('google_calendar_error'))
                <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">{{ session('google_calendar_error') }}</p>
            @endif

            <div class="mt-5 grid gap-4">
                <div class="rounded-md border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-950/60">
                    @if ($googleCalendar['connected'])
                        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                            <div class="flex min-w-0 items-center gap-3">
                                @if ($googleCalendar['account']['picture'] ?? null)
                                    <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                                        <div class="absolute inset-0 flex items-center justify-center">
                                            <span class="absolute inset-x-0 top-0 h-2 bg-sky-500"></span>
                                            <svg class="h-6 w-6 text-slate-700 dark:text-slate-200" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M7 3v3M17 3v3M4.5 9.5h15M6.5 5h11A2.5 2.5 0 0 1 20 7.5v10A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-10A2.5 2.5 0 0 1 6.5 5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                                <path d="m9 14 2 2 4-5" stroke="#0f766e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                            </svg>
                                        </div>
                                        <img src="{{ $googleCalendar['account']['picture'] }}" alt="" class="absolute inset-0 h-full w-full object-cover" referrerpolicy="no-referrer" onerror="this.remove()">
                                    </div>
                                @else
                                    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-md bg-white shadow-sm ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                                        <span class="absolute inset-x-0 top-0 h-2 bg-sky-500"></span>
                                        <svg class="h-6 w-6 text-slate-700 dark:text-slate-200" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M7 3v3M17 3v3M4.5 9.5h15M6.5 5h11A2.5 2.5 0 0 1 20 7.5v10A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5v-10A2.5 2.5 0 0 1 6.5 5Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                                            <path d="m9 14 2 2 4-5" stroke="#0f766e" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                                        </svg>
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $accountName }}</p>
                                    @if ($accountEmail)
                                        <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">{{ $accountEmail }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2 xl:w-[240px]">
                                <div class="rounded-md bg-white p-3 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">This month</p>
                                    <p class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">{{ $monthEventsTotal }}</p>
                                </div>
                                <div class="rounded-md bg-white p-3 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Selected</p>
                                    <p class="mt-1 text-xl font-semibold text-slate-950 dark:text-white">{{ $eventsTotal }}</p>
                                </div>
                            </div>

                            <div class="flex flex-col gap-2 sm:flex-row xl:flex-col">
                                <div class="rounded-md bg-white px-3 py-2 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                                    <p class="text-xs font-medium text-slate-500 dark:text-slate-400">Access</p>
                                    <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">
                                        {{ $googleCalendar['expires_at'] ? \Illuminate\Support\Carbon::createFromTimestamp($googleCalendar['expires_at'])->format('M j, H:i') : 'Active' }}
                                    </p>
                                </div>
                                <form method="POST" action="{{ route('integrations.google-calendar.disconnect') }}">
                                    @csrf
                                    <button type="submit" class="h-10 rounded-md border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:border-rose-300 hover:text-rose-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300 dark:hover:border-rose-500 dark:hover:text-rose-300">Log out calendar</button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="rounded-md bg-white p-3 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-800">
                            <p class="text-sm font-semibold text-slate-950 dark:text-white">Connect campaign calendar</p>
                            <p class="mt-2 text-sm leading-6 text-slate-500 dark:text-slate-400">Show interviews, booking pages, and meetings here before choosing the next company batch.</p>
                        </div>
                        <a href="{{ route('integrations.google-calendar.connect') }}" class="mt-4 inline-flex h-10 w-full items-center justify-center rounded-md bg-slate-950 px-3 text-sm font-semibold text-white shadow-sm hover:bg-slate-800 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200">
                            Connect Google Calendar
                        </a>
                        @unless ($googleCalendar['configured'])
                            <p class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-xs font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">OAuth credentials are not configured yet.</p>
                        @endunless
                    @endif
                </div>

                <div class="rounded-md border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex flex-col gap-3 border-b border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-slate-950 dark:text-white">Upcoming from Google Calendar</h4>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                {{ $selectedDate ? ($googleCalendar['calendar_selected_date_label'] ?? $selectedDate) : $calendarMonth->format('F Y') }} &middot; {{ $eventsTotal }} unique events
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <a href="{{ request()->fullUrlWithQuery(['calendar_month' => $googleCalendar['calendar_previous_month'] ?? $calendarMonth->copy()->subMonthNoOverflow()->format('Y-m'), 'calendar_date' => null, 'calendar_page' => 1]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Prev</a>
                            <span class="rounded-md bg-sky-50 px-2.5 py-1.5 text-xs font-semibold text-sky-700 dark:bg-sky-500/10 dark:text-sky-200">Live</span>
                            <a href="{{ request()->fullUrlWithQuery(['calendar_month' => $googleCalendar['calendar_next_month'] ?? $calendarMonth->copy()->addMonthNoOverflow()->format('Y-m'), 'calendar_date' => null, 'calendar_page' => 1]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Next</a>
                            @if ($selectedDate)
                                <a href="{{ request()->fullUrlWithQuery(['calendar_date' => null, 'calendar_page' => 1]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Month</a>
                            @endif
                        </div>
                    </div>

                    @if ($googleCalendar['events_status'])
                        <div class="p-4">
                            <p class="rounded-md bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">{{ $googleCalendar['events_status'] }}</p>
                        </div>
                    @endif

                    @if ($calendarEvents->isNotEmpty())
                        <div class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($calendarEvents as $event)
                                <article class="grid gap-3 px-4 py-3 sm:grid-cols-[64px_minmax(0,1fr)_auto] sm:items-center">
                                    <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-center dark:border-slate-800 dark:bg-slate-950/70">
                                        <p class="text-[11px] font-semibold uppercase text-slate-500 dark:text-slate-400">{{ $event['day'] }}</p>
                                        <p class="mt-1 text-sm font-semibold text-slate-950 dark:text-white">{{ $event['date'] }}</p>
                                    </div>
                                    <div class="min-w-0">
                                        <h5 class="truncate text-sm font-semibold text-slate-950 dark:text-white">{{ $event['title'] }}</h5>
                                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $event['time'] }} &middot; {{ $event['calendar'] }}</p>
                                        @if (! empty($event['attendees']))
                                            <div class="mt-2 flex flex-wrap gap-1.5" title="{{ $event['attendees_title'] ?? '' }}">
                                                @foreach ($event['attendees'] as $attendee)
                                                    <span class="max-w-full truncate rounded-md border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-medium text-slate-600 dark:border-slate-800 dark:bg-slate-950/70 dark:text-slate-300">{{ $attendee }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex items-center gap-2">
                                        @if ($event['meeting_link'])
                                            <a href="{{ $event['meeting_link'] }}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-teal-300 hover:text-teal-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-teal-500 dark:hover:text-teal-300">Meet</a>
                                        @endif
                                        @if ($event['link'])
                                            <a href="{{ $event['link'] }}" target="_blank" rel="noreferrer" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Open</a>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        @if ($eventsLastPage > 1)
                            <div class="flex flex-col gap-3 border-t border-slate-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Page {{ $eventsPage }} of {{ $eventsLastPage }} &middot; {{ $eventsPerPage }} per page</p>
                                <div class="flex items-center gap-2">
                                    @if ($eventsPage > 1)
                                        <a href="{{ request()->fullUrlWithQuery(['calendar_page' => $eventsPage - 1]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Previous</a>
                                    @else
                                        <span class="rounded-md border border-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600">Previous</span>
                                    @endif

                                    @if ($eventsPage < $eventsLastPage)
                                        <a href="{{ request()->fullUrlWithQuery(['calendar_page' => $eventsPage + 1]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:border-sky-300 hover:text-sky-800 dark:border-slate-800 dark:text-slate-300 dark:hover:border-sky-500 dark:hover:text-sky-300">Next page</a>
                                    @else
                                        <span class="rounded-md border border-slate-100 px-2.5 py-1.5 text-xs font-semibold text-slate-300 dark:border-slate-800 dark:text-slate-600">Next page</span>
                                    @endif
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="p-4">
                            <div class="rounded-md border border-dashed border-slate-300 bg-slate-50 px-4 py-6 text-center dark:border-slate-700 dark:bg-slate-950/60">
                                <h5 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $googleCalendar['connected'] ? 'No upcoming events found' : 'Calendar data will appear after connection' }}</h5>
                                <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $googleCalendar['connected'] ? 'Google Calendar returned no events for this month.' : 'Connect Google Calendar to preview meetings before the company queue.' }}</p>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <aside class="bg-slate-50 p-4 dark:bg-slate-950/50">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h4 class="text-sm font-semibold text-slate-950 dark:text-white">{{ $calendarMonth->format('F Y') }}</h4>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Campaign scheduling map</p>
                </div>
                <div class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <span class="h-2 w-2 rounded-full bg-teal-500"></span>
                    Event day
                </div>
            </div>

            <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase text-slate-400 dark:text-slate-500">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                    <span class="py-1">{{ $weekday }}</span>
                @endforeach
            </div>

            <div class="mt-1 grid grid-cols-7 gap-1">
                @foreach ($calendarDays as $day)
                    @php
                        $dateKey = $day->toDateString();
                        $hasEvents = $eventDates->has($dateKey);
                        $isSelectedDate = $selectedDate === $dateKey;
                        $dayClass = $isSelectedDate
                            ? 'border-teal-600 bg-teal-600 text-white shadow-sm dark:border-teal-400 dark:bg-teal-500 dark:text-slate-950'
                            : ($day->isSameMonth($calendarMonth)
                                ? 'border-slate-200 bg-white text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200'
                                : 'border-transparent text-slate-300 dark:text-slate-700');
                        $todayClass = ! $isSelectedDate && $day->isToday()
                            ? 'ring-2 ring-slate-950 ring-offset-1 ring-offset-slate-50 dark:ring-white dark:ring-offset-slate-950'
                            : '';
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['calendar_month' => $day->format('Y-m'), 'calendar_date' => $dateKey, 'calendar_page' => 1]) }}" class="relative flex aspect-square min-h-10 items-center justify-center rounded-md border text-sm font-semibold transition hover:border-teal-300 hover:text-teal-800 dark:hover:border-teal-500 dark:hover:text-teal-300 {{ $dayClass }} {{ $todayClass }}">
                        {{ $day->day }}
                        @if ($hasEvents)
                            <span class="absolute bottom-1.5 h-1.5 w-1.5 rounded-full {{ $isSelectedDate ? 'bg-white dark:bg-slate-950' : 'bg-teal-500' }}"></span>
                        @endif
                    </a>
                @endforeach
            </div>

            <div class="mt-4 grid gap-2">
                @forelse ($calendarEvents->take(3) as $event)
                    <div class="rounded-md border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-center justify-between gap-3">
                            <p class="truncate text-xs font-semibold text-slate-950 dark:text-white">{{ $event['title'] }}</p>
                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">{{ $event['date'] }}</span>
                        </div>
                        <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400">{{ $event['time'] }}</p>
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-slate-300 bg-white px-3 py-4 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">
                        No event markers yet.
                    </div>
                @endforelse
            </div>
        </aside>
    </div>
</section>
