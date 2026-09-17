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

    private function normalizeEmail(string $email): string
    {
        return str($email)->lower()->trim()->toString();
    }
}
