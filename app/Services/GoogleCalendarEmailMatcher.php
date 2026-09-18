<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class GoogleCalendarEmailMatcher
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function match(array $state): array
    {
        $visibleEvents = collect($state['events'] ?? []);
        $windowEvents = collect($state['calendar_events_window'] ?? []);
        $eventsForLookup = $windowEvents->isNotEmpty() ? $windowEvents : $visibleEvents;
        $emails = $eventsForLookup
            ->flatMap(fn (array $event): array => collect($event['attendee_people'] ?? [])
                ->pluck('email')
                ->filter()
                ->all())
            ->map(fn (string $email): string => $this->normalizeEmail($email))
            ->filter()
            ->unique()
            ->values();

        if ($eventsForLookup->isEmpty() || $emails->isEmpty()) {
            $state['calendar_email_compare'] = [
                'checked' => 0,
                'matched' => 0,
                'unmatched' => 0,
                'available' => true,
            ];
            $state['calendar_month_email_matches'] = $this->emptyMonthMatches();

            return $state;
        }

        try {
            $contactsByEmail = GhlContact::query()
                ->whereNotNull('email')
                ->whereIn(DB::raw('lower(email)'), $emails->all())
                ->get(['ghl_contact_id', 'name', 'email', 'business'])
                ->groupBy(fn (GhlContact $contact): string => $this->normalizeEmail((string) $contact->email));
        } catch (Throwable) {
            $state['calendar_email_compare'] = [
                'checked' => $emails->count(),
                'matched' => 0,
                'unmatched' => 0,
                'available' => false,
            ];
            $state['calendar_month_email_matches'] = $this->emptyMonthMatches(false);

            return $state;
        }

        $state['calendar_events_window'] = $this->eventsWithMatches($windowEvents, $contactsByEmail);
        $state['events'] = $this->eventsWithMatches($visibleEvents, $contactsByEmail);
        $visibleCompare = $this->compare($state['events']);

        $state['calendar_email_compare'] = [
            'checked' => $visibleCompare['matched'] + $visibleCompare['unmatched'],
            'matched' => $visibleCompare['matched'],
            'unmatched' => $visibleCompare['unmatched'],
            'available' => true,
        ];
        $state['calendar_month_email_matches'] = $this->monthMatches($state['calendar_events_window']);

        return $state;
    }

    private function eventsWithMatches(Collection $events, Collection $contactsByEmail): array
    {
        return $events
            ->map(function (array $event) use ($contactsByEmail): array {
                $event['attendee_matches'] = collect($event['attendee_people'] ?? [])
                    ->map(function (array $attendee) use ($contactsByEmail): array {
                        $email = $this->normalizeEmail((string) ($attendee['email'] ?? ''));
                        $contacts = $email !== '' ? $contactsByEmail->get($email, collect()) : collect();
                        $contact = $contacts->first();
                        $matchCount = $contacts->count();

                        return [
                            'label' => $attendee['label'] ?? $email,
                            'email' => $email,
                            'matched' => $contact !== null,
                            'match_count' => $matchCount,
                            'contact_name' => $contact?->name,
                            'business' => $contact?->business,
                            'businesses' => $contacts
                                ->pluck('business')
                                ->filter()
                                ->unique()
                                ->values()
                                ->all(),
                        ];
                    })
                    ->values()
                    ->all();

                return $event;
            })
            ->values()
            ->all();
    }

    private function compare(array $events): array
    {
        return collect($events)
            ->flatMap(fn (array $event): array => $event['attendee_matches'] ?? [])
            ->reduce(fn (array $carry, array $attendee): array => [
                'matched' => $carry['matched'] + (($attendee['matched'] ?? false) ? 1 : 0),
                'unmatched' => $carry['unmatched'] + (($attendee['matched'] ?? false) ? 0 : 1),
            ], ['matched' => 0, 'unmatched' => 0]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<string, mixed>
     */
    private function monthMatches(array $events): array
    {
        $matches = collect($events)
            ->flatMap(function (array $event): array {
                return collect($event['attendee_matches'] ?? [])
                    ->filter(fn (array $attendee): bool => (bool) ($attendee['matched'] ?? false))
                    ->map(function (array $attendee) use ($event): array {
                        return [
                            'email' => $this->normalizeEmail((string) ($attendee['email'] ?? '')),
                            'label' => $attendee['label'] ?? $attendee['email'] ?? '',
                            'contact_name' => $attendee['contact_name'] ?? null,
                            'match_count' => (int) ($attendee['match_count'] ?? 0),
                            'businesses' => $attendee['businesses'] ?? [],
                            'event' => collect($event)->only([
                                'title',
                                'date',
                                'date_key',
                                'time',
                                'calendar',
                                'link',
                                'meeting_link',
                            ])->all(),
                        ];
                    })
                    ->filter(fn (array $match): bool => $match['email'] !== '')
                    ->all();
            })
            ->groupBy('email')
            ->map(function (Collection $matches, string $email): array {
                $events = $matches
                    ->pluck('event')
                    ->unique(fn (array $event): string => implode('|', [
                        $event['date_key'] ?? '',
                        $event['time'] ?? '',
                        $event['title'] ?? '',
                    ]))
                    ->values();

                return [
                    'email' => $email,
                    'label' => $matches->pluck('label')->filter()->first() ?: $email,
                    'contact_names' => $matches->pluck('contact_name')->filter()->unique()->values()->all(),
                    'businesses' => $matches
                        ->flatMap(fn (array $match): array => $match['businesses'] ?? [])
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'match_count' => max($matches->pluck('match_count')->max() ?? 1, 1),
                    'events_count' => $events->count(),
                    'events' => $events->all(),
                ];
            })
            ->sortBy('email')
            ->values();

        return [
            'available' => true,
            'emails_count' => $matches->count(),
            'events_count' => $matches->sum('events_count'),
            'items' => $matches->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyMonthMatches(bool $available = true): array
    {
        return [
            'available' => $available,
            'emails_count' => 0,
            'events_count' => 0,
            'items' => [],
        ];
    }

    private function normalizeEmail(string $email): string
    {
        return str($email)->lower()->trim()->toString();
    }
}
