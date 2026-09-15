<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GoogleCalendarEventReader
{
    private const CALENDAR_LIST_URL = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    private const EVENTS_BASE_URL = 'https://www.googleapis.com/calendar/v3/calendars';

    private const EventsPerPage = 6;

    private const MaxCalendars = 12;

    private const MaxEventsPerCalendar = 100;

    /**
     * @return array<string, mixed>
     */
    public function upcoming(string $accessToken, mixed $month = null, mixed $date = null, int $page = 1): array
    {
        $selectedDate = $this->selectedDate($date);
        $monthStart = $selectedDate?->copy()->startOfMonth() ?? $this->monthStart($month);
        $monthEnd = $monthStart->copy()->endOfMonth();
        $calendars = $this->visibleCalendars($accessToken);
        $events = collect($calendars)
            ->flatMap(fn (array $calendar): array => $this->calendarEvents($accessToken, $calendar, $monthStart, $monthEnd))
            ->unique('dedupe_key')
            ->sortBy('starts_at')
            ->values();
        $displayEvents = $selectedDate
            ? $events->where('date_key', $selectedDate->toDateString())->values()
            : $events;
        $lastPage = max((int) ceil($displayEvents->count() / self::EventsPerPage), 1);
        $page = min(max($page, 1), $lastPage);
        $visibleEvents = $displayEvents
            ->forPage($page, self::EventsPerPage)
            ->map(fn (array $event): array => collect($event)->except(['starts_at', 'dedupe_key'])->all())
            ->values()
            ->all();

        if ($calendars === [] && $events->isEmpty()) {
            return ['events_status' => 'Upcoming events could not be reached right now.'];
        }

        $state = [
            'events' => $visibleEvents,
            'event_dates' => $events->pluck('date_key')->unique()->values()->all(),
            'events_total' => $displayEvents->count(),
            'calendar_month_events_total' => $events->count(),
            'events_page' => $page,
            'events_last_page' => $lastPage,
            'events_per_page' => self::EventsPerPage,
            'calendar_month' => $monthStart->format('Y-m'),
            'calendar_month_label' => $monthStart->format('F Y'),
            'calendar_selected_date' => $selectedDate?->toDateString(),
            'calendar_selected_date_label' => $selectedDate?->format('M j, Y'),
            'calendar_previous_month' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
            'calendar_next_month' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
        ];

        if (($calendars[0]['fallback'] ?? false) === true) {
            $state['events_status'] = 'Reconnect Google Calendar to include shared team calendars.';
        }

        return $state;
    }

    /**
     * @return array<int, array{id: string, title: string, fallback?: bool}>
     */
    private function visibleCalendars(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)->timeout(10)->get(self::CALENDAR_LIST_URL, [
                'maxResults' => 50,
                'showHidden' => 'false',
            ]);
        } catch (ConnectionException) {
            return [];
        }

        if ($response->failed()) {
            return [['id' => 'primary', 'title' => 'Primary calendar', 'fallback' => true]];
        }

        $calendars = collect($response->json('items', []))
            ->filter(fn (mixed $calendar): bool => is_array($calendar) && ! $this->isNoiseCalendar($calendar))
            ->sortByDesc(fn (array $calendar): int => ($calendar['primary'] ?? false) ? 2 : (($calendar['selected'] ?? false) ? 1 : 0))
            ->map(fn (array $calendar): array => [
                'id' => (string) $calendar['id'],
                'title' => (string) ($calendar['summaryOverride'] ?? $calendar['summary'] ?? 'Calendar'),
            ])
            ->take(self::MaxCalendars)
            ->values()
            ->all();

        return $calendars ?: [['id' => 'primary', 'title' => 'Primary calendar']];
    }

    /**
     * @param  array{id: string, title: string}  $calendar
     * @return array<int, array<string, mixed>>
     */
    private function calendarEvents(string $accessToken, array $calendar, Carbon $monthStart, Carbon $monthEnd): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get(self::EVENTS_BASE_URL.'/'.rawurlencode($calendar['id']).'/events', [
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'maxResults' => self::MaxEventsPerCalendar,
                    'timeMin' => $monthStart->copy()->startOfDay()->toRfc3339String(),
                    'timeMax' => $monthEnd->copy()->endOfDay()->toRfc3339String(),
                ]);
        } catch (ConnectionException) {
            return [];
        }

        return $response->failed()
            ? []
            : collect($response->json('items', []))
                ->map(fn (array $event): ?array => $this->formatEvent($event, $calendar['title']))
                ->filter()
                ->values()
                ->all();
    }

    private function formatEvent(array $event, string $calendarTitle): ?array
    {
        $title = trim((string) ($event['summary'] ?? ''));
        $start = $event['start']['dateTime'] ?? $event['start']['date'] ?? null;

        if ($title === '' || blank($start)) {
            return null;
        }

        $startsAt = Carbon::parse($start);
        $end = $event['end']['dateTime'] ?? $event['end']['date'] ?? null;
        $endsAt = filled($end) ? Carbon::parse($end) : null;
        $displayStartsAt = $this->displayTime($startsAt);
        $displayEndsAt = $endsAt ? $this->displayTime($endsAt) : null;
        $attendees = $this->attendees($event);

        return [
            'title' => $title,
            'starts_at' => $startsAt->timestamp,
            'dedupe_key' => $this->dedupeKey($event, $startsAt, $endsAt),
            'date_key' => $displayStartsAt->toDateString(),
            'day' => $displayStartsAt->format('D'),
            'date' => $displayStartsAt->format('M j'),
            'time' => isset($event['start']['date']) ? 'All day' : $displayStartsAt->format('H:i').($displayEndsAt ? ' - '.$displayEndsAt->format('H:i') : ''),
            'calendar' => $calendarTitle,
            'attendees' => $attendees,
            'attendees_title' => collect($attendees)->implode(', ') ?: $calendarTitle,
            'link' => $event['htmlLink'] ?? null,
            'meeting_link' => $event['hangoutLink'] ?? null,
            'status' => $event['status'] ?? 'confirmed',
        ];
    }

    private function monthStart(mixed $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month, $this->displayTimezone())->startOfMonth();
        }

        return now($this->displayTimezone())->startOfMonth();
    }

    private function selectedDate(mixed $date): ?Carbon
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::parse($date, $this->displayTimezone())->startOfDay();
        }

        return null;
    }

    private function displayTime(Carbon $time): Carbon
    {
        return $time->copy()->setTimezone($this->displayTimezone());
    }

    private function displayTimezone(): string
    {
        return (string) config('services.google_calendar.timezone', 'Asia/Bangkok');
    }

    /**
     * @return array<int, string>
     */
    private function attendees(array $event): array
    {
        return collect($event['attendees'] ?? [])
            ->filter(fn (mixed $attendee): bool => is_array($attendee))
            ->map(fn (array $attendee): string => trim((string) ($attendee['displayName'] ?? $attendee['email'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function isNoiseCalendar(array $calendar): bool
    {
        $id = (string) ($calendar['id'] ?? '');
        $title = str((string) ($calendar['summary'] ?? ''))->lower()->toString();

        return (bool) ($calendar['deleted'] ?? false)
            || (bool) ($calendar['hidden'] ?? false)
            || str_contains($id, '#holiday')
            || str_contains($id, '#contacts')
            || in_array($title, ['birthdays', 'tasks'], true);
    }

    private function dedupeKey(array $event, Carbon $startsAt, ?Carbon $endsAt): string
    {
        return str((string) ($event['summary'] ?? 'Untitled calendar event'))
            ->lower()
            ->squish()
            ->append('|'.$startsAt->timestamp.'|'.($endsAt?->timestamp ?? ''))
            ->toString();
    }
}
