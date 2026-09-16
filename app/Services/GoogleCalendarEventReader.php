<?php

namespace App\Services;

use Illuminate\Support\Carbon;

class GoogleCalendarEventReader
{
    private const InternalAttendeeDomains = ['top4.com.au', 'top4.online'];

    private const InternalAttendeeNames = ['michael doyle'];

    private const EventsPerPage = 6;

    public function __construct(private readonly GoogleCalendarApiClient $calendarApi) {}

    /**
     * @return array<string, mixed>
     */
    public function upcoming(
        string $accessToken,
        mixed $month = null,
        mixed $date = null,
        int $page = 1,
        array $dateRange = [],
        ?string $cacheScope = null,
    ): array
    {
        $cacheScope ??= 'default';
        $selectedDate = $this->selectedDate($date);
        $rangeStart = $this->rangeDate($dateRange['from'] ?? null)?->startOfDay();
        $rangeEnd = $this->rangeDate($dateRange['to'] ?? null)?->endOfDay();
        $hasDateRange = $rangeStart !== null || $rangeEnd !== null;
        $monthStart = $selectedDate?->copy()->startOfMonth() ?? $this->monthStart($month, $rangeStart);
        $monthEnd = $monthStart->copy()->endOfMonth();
        $eventsStart = $rangeStart ?? $monthStart->copy()->startOfDay();
        $eventsEnd = $rangeEnd ?? $monthEnd->copy()->endOfDay();
        if ($eventsStart->greaterThan($eventsEnd)) {
            [$eventsStart, $eventsEnd] = [$eventsEnd->copy()->startOfDay(), $eventsStart->copy()->endOfDay()];
        }

        $calendars = $this->calendarApi->visibleCalendars($accessToken, $cacheScope);
        $events = collect($this->calendarApi->eventsForWindow($accessToken, $calendars, $eventsStart, $eventsEnd, $cacheScope))
            ->map(fn (array $item): ?array => $this->formatEvent($item['event'], $item['calendar_title']))
            ->filter()
            ->unique('dedupe_key')
            ->sortBy('starts_at')
            ->values();
        $windowEvents = $events
            ->map(fn (array $event): array => collect($event)->except(['starts_at', 'dedupe_key'])->all())
            ->values()
            ->all();
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
            'calendar_events_window' => $windowEvents,
            'event_dates' => $events->pluck('date_key')->unique()->values()->all(),
            'events_total' => $displayEvents->count(),
            'calendar_month_events_total' => $events->count(),
            'calendar_metric_label' => $hasDateRange ? 'This range' : 'This month',
            'events_page' => $page,
            'events_last_page' => $lastPage,
            'events_per_page' => self::EventsPerPage,
            'calendar_month' => $monthStart->format('Y-m'),
            'calendar_month_label' => $monthStart->format('F Y'),
            'calendar_selected_date' => $selectedDate?->toDateString(),
            'calendar_selected_date_label' => $selectedDate?->format('M j, Y'),
            'calendar_has_date_range' => $hasDateRange,
            'calendar_range_from' => $rangeStart?->toDateString(),
            'calendar_range_to' => $rangeEnd?->toDateString(),
            'calendar_range_label' => $hasDateRange ? $this->rangeLabel($rangeStart, $rangeEnd) : null,
            'calendar_previous_month' => $monthStart->copy()->subMonthNoOverflow()->format('Y-m'),
            'calendar_next_month' => $monthStart->copy()->addMonthNoOverflow()->format('Y-m'),
        ];

        if (($calendars[0]['fallback'] ?? false) === true) {
            $state['events_status'] = 'Reconnect Google Calendar to include shared team calendars.';
        }

        return $state;
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
        $attendeeLabels = collect($attendees)->pluck('label')->all();

        return [
            'title' => $title,
            'starts_at' => $startsAt->timestamp,
            'dedupe_key' => $this->dedupeKey($event, $startsAt, $endsAt),
            'date_key' => $displayStartsAt->toDateString(),
            'day' => $displayStartsAt->format('D'),
            'date' => $displayStartsAt->format('M j'),
            'time' => isset($event['start']['date']) ? 'All day' : $displayStartsAt->format('H:i').($displayEndsAt ? ' - '.$displayEndsAt->format('H:i') : ''),
            'calendar' => $this->displayCalendarTitle($calendarTitle),
            'attendees' => $attendeeLabels,
            'attendee_people' => $attendees,
            'attendees_title' => collect($attendeeLabels)->implode(', ') ?: $calendarTitle,
            'link' => $event['htmlLink'] ?? null,
            'meeting_link' => $event['hangoutLink'] ?? null,
            'status' => $event['status'] ?? 'confirmed',
        ];
    }

    private function monthStart(mixed $month, ?Carbon $fallback = null): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month, $this->displayTimezone())->startOfMonth();
        }

        return ($fallback ?? now($this->displayTimezone()))->copy()->startOfMonth();
    }

    private function selectedDate(mixed $date): ?Carbon
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::parse($date, $this->displayTimezone())->startOfDay();
        }

        return null;
    }

    private function rangeDate(mixed $date): ?Carbon
    {
        if (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::parse($date, $this->displayTimezone());
        }

        return null;
    }

    private function rangeLabel(?Carbon $from, ?Carbon $to): string
    {
        return match (true) {
            $from !== null && $to !== null => $from->format('M j, Y').' - '.$to->format('M j, Y'),
            $from !== null => 'From '.$from->format('M j, Y'),
            $to !== null => 'Until '.$to->format('M j, Y'),
            default => '',
        };
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
     * @return array<int, array{label: string, email: string|null}>
     */
    private function attendees(array $event): array
    {
        return collect($event['attendees'] ?? [])
            ->filter(fn (mixed $attendee): bool => is_array($attendee))
            ->reject(fn (array $attendee): bool => $this->isInternalAttendee($attendee))
            ->map(fn (array $attendee): array => [
                'label' => trim((string) ($attendee['displayName'] ?? $attendee['email'] ?? '')),
                'email' => filled($attendee['email'] ?? null) ? strtolower(trim((string) $attendee['email'])) : null,
            ])
            ->filter(fn (array $attendee): bool => $attendee['label'] !== '')
            ->unique(fn (array $attendee): string => $attendee['email'] ?: $attendee['label'])
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attendee
     */
    private function isInternalAttendee(array $attendee): bool
    {
        $email = str((string) ($attendee['email'] ?? ''))->lower()->trim()->toString();
        $name = str((string) ($attendee['displayName'] ?? ''))->lower()->squish()->toString();

        if (str_contains($email, 'top4') || str_contains($name, 'top4')) {
            return true;
        }

        if ($email !== '') {
            foreach (self::InternalAttendeeDomains as $domain) {
                if (str_ends_with($email, '@'.$domain)) {
                    return true;
                }
            }
        }

        return $name !== '' && in_array($name, self::InternalAttendeeNames, true);
    }

    private function displayCalendarTitle(string $calendarTitle): string
    {
        return str_contains(strtolower($calendarTitle), 'top4') ? 'Calendar' : $calendarTitle;
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
