<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class GhlContactMapper
{
    public function __construct(
        private readonly GhlContactDateRange $dateRange,
    ) {}

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function databaseRow(array $contact): array
    {
        $createdAt = (string) (Arr::get($contact, 'dateAdded')
            ?? Arr::get($contact, 'date_added')
            ?? Arr::get($contact, 'createdAt')
            ?? Arr::get($contact, 'created_at')
            ?? Arr::get($contact, 'created')
            ?? Arr::get($contact, 'dateCreated')
            ?? '');

        return [
            'ghl_contact_id' => (string) (Arr::get($contact, 'id') ?? Arr::get($contact, 'contactId') ?? Arr::get($contact, 'contact_id')),
            'name' => $this->name($contact),
            'email' => (string) Arr::get($contact, 'email', ''),
            'phone' => (string) Arr::get($contact, 'phone', ''),
            'business' => $this->business($contact),
            'website' => (string) (Arr::get($contact, 'website') ?? Arr::get($contact, 'websiteUrl') ?? ''),
            'created_at_ghl' => $this->dateTime($createdAt),
            'created_date' => $this->dateRange->dateOnly($createdAt) ?: null,
            'tags' => $this->tags($contact),
            'custom_fields' => Arr::get($contact, 'customFields', Arr::get($contact, 'custom_fields', [])),
            'audit_report_url' => $this->auditReportUrl($contact),
            'raw_payload' => $contact,
            'synced_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    private function name(array $contact): string
    {
        return trim((string) (Arr::get($contact, 'firstName') ?? Arr::get($contact, 'first_name') ?? '').' '.(string) (Arr::get($contact, 'lastName') ?? Arr::get($contact, 'last_name') ?? ''))
            ?: (string) (Arr::get($contact, 'contactName') ?? Arr::get($contact, 'fullName') ?? Arr::get($contact, 'name') ?? 'Unnamed');
    }

    private function business(array $contact): string
    {
        return (string) (Arr::get($contact, 'businessName')
            ?? Arr::get($contact, 'companyName')
            ?? Arr::get($contact, 'company_name')
            ?? Arr::get($contact, 'company.name')
            ?? Arr::get($contact, 'company')
            ?? Arr::get($contact, 'website')
            ?? '');
    }

    private function dateTime(string $date): ?Carbon
    {
        try {
            return $date === '' ? null : Carbon::parse($date);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, string>
     */
    private function tags(array $contact): array
    {
        $tags = Arr::get($contact, 'tags', []);

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        return collect($tags)
            ->filter(fn (mixed $tag): bool => is_scalar($tag))
            ->map(fn (mixed $tag): string => trim((string) $tag))
            ->filter()
            ->values()
            ->all();
    }

    private function auditReportUrl(array $contact): ?string
    {
        $fieldId = (string) config('services.ghl.audit_report_url_field_id');
        $field = collect(Arr::get($contact, 'customFields', Arr::get($contact, 'custom_fields', [])))
            ->first(fn (mixed $field): bool => is_array($field) && (string) Arr::get($field, 'id') === $fieldId);

        return filled(Arr::get($field, 'value')) ? (string) Arr::get($field, 'value') : null;
    }
}
