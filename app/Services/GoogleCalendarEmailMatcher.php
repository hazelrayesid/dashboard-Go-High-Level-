<?php

namespace App\Services;

use App\Models\GhlContact;
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
        $events = collect($state['events'] ?? []);
        $emails = $events
            ->flatMap(fn (array $event): array => collect($event['attendee_people'] ?? [])
                ->pluck('email')
                ->filter()
                ->all())
            ->map(fn (string $email): string => $this->normalizeEmail($email))
            ->filter()
            ->unique()
            ->values();

        if ($events->isEmpty() || $emails->isEmpty()) {
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

        $matched = 0;
        $unmatched = 0;

        $state['events'] = $events
            ->map(function (array $event) use ($contactsByEmail, &$matched, &$unmatched): array {
                $event['attendee_matches'] = collect($event['attendee_people'] ?? [])
                    ->map(function (array $attendee) use ($contactsByEmail, &$matched, &$unmatched): array {
                        $email = $this->normalizeEmail((string) ($attendee['email'] ?? ''));
                        $contacts = $email !== '' ? $contactsByEmail->get($email, collect()) : collect();
                        $contact = $contacts->first();
                        $isMatched = $contact !== null;

                        $isMatched ? $matched++ : $unmatched++;

                        return [
                            'label' => $attendee['label'] ?? $email,
                            'email' => $email,
                            'matched' => $isMatched,
                            'contact_name' => $contact?->name,
                            'business' => $contact?->business,
                        ];
                    })
                    ->values()
                    ->all();

                return $event;
            })
            ->values()
            ->all();

        $state['calendar_email_compare'] = [
            'checked' => $matched + $unmatched,
            'matched' => $matched,
            'unmatched' => $unmatched,
            'available' => true,
        ];

        return $state;
    }

    private function normalizeEmail(string $email): string
    {
        return str($email)->lower()->trim()->toString();
    }
}
