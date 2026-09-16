<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GoogleCalendarApiClient
{
    private const CALENDAR_LIST_URL = 'https://www.googleapis.com/calendar/v3/users/me/calendarList';

    private const EVENTS_BASE_URL = 'https://www.googleapis.com/calendar/v3/calendars';

    private const MaxCalendars = 12;

    private const MaxEventsPerCalendar = 100;

    private const CalendarListCacheHours = 6;

    private const EventsCacheMinutes = 3;

    /**
     * @return array<int, array{id: string, title: string, fallback?: bool}>
     */
    public function visibleCalendars(string $accessToken, string $cacheScope): array
    {
        $cacheKey = $this->calendarListCacheKey($cacheScope);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

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

        $calendars = $calendars ?: [['id' => 'primary', 'title' => 'Primary calendar']];
        Cache::put($cacheKey, $calendars, now()->addHours(self::CalendarListCacheHours));

        return $calendars;
    }

    /**
     * @param  array<int, array{id: string, title: string}>  $calendars
     * @return array<int, array{calendar_title: string, event: array<string, mixed>}>
     */
    public function eventsForWindow(string $accessToken, array $calendars, Carbon $eventsStart, Carbon $eventsEnd, string $cacheScope): array
    {
        $cacheKey = $this->eventsCacheKey($cacheScope, $eventsStart, $eventsEnd, $calendars);
        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $events = $this->calendarEventsBatch($accessToken, $calendars, $eventsStart, $eventsEnd);
        Cache::put($cacheKey, $events, now()->addMinutes(self::EventsCacheMinutes));

        return $events;
    }

    private function calendarEventsBatch(string $accessToken, array $calendars, Carbon $eventsStart, Carbon $eventsEnd): array
    {
        if ($calendars === []) {
            return [];
        }

        try {
            $responses = Http::pool(function (Pool $pool) use ($accessToken, $calendars, $eventsStart, $eventsEnd): void {
                foreach ($calendars as $index => $calendar) {
                    $pool->as((string) $index)
                        ->withToken($accessToken)
                        ->timeout(10)
                        ->get(self::EVENTS_BASE_URL.'/'.rawurlencode($calendar['id']).'/events', [
                            'singleEvents' => 'true',
                            'orderBy' => 'startTime',
                            'maxResults' => self::MaxEventsPerCalendar,
                            'timeMin' => $eventsStart->copy()->startOfDay()->toRfc3339String(),
                            'timeMax' => $eventsEnd->copy()->endOfDay()->toRfc3339String(),
                        ]);
                }
            });
        } catch (ConnectionException) {
            return [];
        }

        return collect($calendars)
            ->flatMap(fn (array $calendar, int $index): array => $this->eventsFromResponse($responses[(string) $index] ?? null, $calendar['title']))
            ->values()
            ->all();
    }

    private function eventsFromResponse(mixed $response, string $calendarTitle): array
    {
        if (! $response instanceof Response || $response->failed()) {
            return [];
        }

        return collect($response->json('items', []))
            ->filter(fn (mixed $event): bool => is_array($event))
            ->map(fn (array $event): array => ['calendar_title' => $calendarTitle, 'event' => $event])
            ->values()
            ->all();
    }

    private function calendarListCacheKey(string $cacheScope): string
    {
        return 'google-calendar:list:'.$cacheScope;
    }

    private function eventsCacheKey(string $cacheScope, Carbon $eventsStart, Carbon $eventsEnd, array $calendars): string
    {
        return 'google-calendar:events:'.hash('sha256', json_encode([
            'scope' => $cacheScope,
            'timezone' => (string) config('services.google_calendar.timezone', 'Asia/Bangkok'),
            'from' => $eventsStart->toRfc3339String(),
            'to' => $eventsEnd->toRfc3339String(),
            'calendars' => collect($calendars)->pluck('id')->values()->all(),
        ]));
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
}
