<?php

namespace App\Services;

use App\Models\GhlContact;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class GhlContactWebhookService
{
    public function __construct(
        private readonly GhlClient $client,
        private readonly GhlContactMapper $mapper,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{action: string, contact_id: string|null}
     */
    public function handle(array $payload): array
    {
        $contactId = $this->contactId($payload);

        if ($contactId === null) {
            return ['action' => 'ignored', 'contact_id' => null];
        }

        if ($this->isDeleteEvent($payload)) {
            GhlContact::query()
                ->where('ghl_contact_id', $contactId)
                ->delete();

            Cache::flush();

            return ['action' => 'deleted', 'contact_id' => $contactId];
        }

        $contact = $this->freshContact($contactId) ?? $this->contactPayload($payload);
        $contact['id'] = $contactId;

        $model = GhlContact::withTrashed()->updateOrCreate(
            ['ghl_contact_id' => $contactId],
            $this->mapper->databaseRow($contact),
        );

        if ($model->trashed()) {
            $model->restore();
        }

        Cache::flush();

        return ['action' => 'upserted', 'contact_id' => $contactId];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isDeleteEvent(array $payload): bool
    {
        $event = strtolower((string) (Arr::get($payload, 'type')
            ?? Arr::get($payload, 'event')
            ?? Arr::get($payload, 'eventType')
            ?? Arr::get($payload, 'messageType')
            ?? ''));

        return str_contains($event, 'delete')
            || str_contains($event, 'deleted')
            || str_contains($event, 'remove');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function contactId(array $payload): ?string
    {
        $id = Arr::get($payload, 'contact.id')
            ?? Arr::get($payload, 'contact.contactId')
            ?? Arr::get($payload, 'data.contact.id')
            ?? Arr::get($payload, 'data.id')
            ?? Arr::get($payload, 'data.contactId')
            ?? Arr::get($payload, 'id')
            ?? Arr::get($payload, 'contactId')
            ?? Arr::get($payload, 'contact_id');

        return filled($id) ? (string) $id : null;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function contactPayload(array $payload): array
    {
        $contact = Arr::get($payload, 'contact')
            ?? Arr::get($payload, 'data.contact')
            ?? Arr::get($payload, 'data')
            ?? $payload;

        return is_array($contact) ? $contact : $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function freshContact(string $contactId): ?array
    {
        $result = $this->client->contactById($contactId);

        if (! $result['ok']) {
            return null;
        }

        $contact = Arr::get($result, 'data.contact') ?? Arr::get($result, 'data');

        return is_array($contact) ? $contact : null;
    }
}
