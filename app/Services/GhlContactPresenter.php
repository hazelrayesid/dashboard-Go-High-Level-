<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class GhlContactPresenter
{
    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    public function contacts(?array $payload): array
    {
        return $this->companies($payload, false);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<int, array<string, mixed>>
     */
    public function companies(?array $payload, bool $requireCompany = true): array
    {
        return collect(Arr::get($payload, 'contacts', []))
            ->filter(fn (mixed $contact): bool => is_array($contact))
            ->map(fn (array $contact): array => $this->present($contact))
            ->filter(fn (array $contact): bool => ! $requireCompany || $contact['business'] !== '-')
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    public function hasDisplayableCompany(array $contact): bool
    {
        return $this->present($contact)['business'] !== '-';
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     */
    public function matchesDateRange(array $contact, array $dateRange): bool
    {
        if (blank($dateRange['from'] ?? null) && blank($dateRange['to'] ?? null)) {
            return true;
        }

        $date = $this->dateOnly((string) (Arr::get($contact, 'dateAdded')
            ?? Arr::get($contact, 'createdAt')
            ?? Arr::get($contact, 'created')
            ?? '-'));

        if ($date === '') {
            return false;
        }

        return (blank($dateRange['from'] ?? null) || $date >= $dateRange['from'])
            && (blank($dateRange['to'] ?? null) || $date <= $dateRange['to']);
    }

    /**
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    private function present(array $contact): array
    {
        $createdAt = (string) (Arr::get($contact, 'dateAdded')
            ?? Arr::get($contact, 'createdAt')
            ?? Arr::get($contact, 'created')
            ?? '-');

        return [
            'id' => (string) Arr::get($contact, 'id', ''),
            'name' => trim((string) Arr::get($contact, 'firstName', '').' '.(string) Arr::get($contact, 'lastName', '')) ?: (string) Arr::get($contact, 'contactName', 'Unnamed'),
            'email' => (string) Arr::get($contact, 'email', '-'),
            'phone' => (string) Arr::get($contact, 'phone', '-'),
            'business' => (string) (Arr::get($contact, 'businessName')
                ?? Arr::get($contact, 'companyName')
                ?? Arr::get($contact, 'company.name')
                ?? Arr::get($contact, 'website')
                ?? '-'),
            'created_at' => $createdAt,
            'created_date' => $this->dateOnly($createdAt),
            'tags' => collect(Arr::get($contact, 'tags', []))
                ->filter(fn (mixed $tag): bool => is_scalar($tag))
                ->map(fn (mixed $tag): string => (string) $tag)
                ->values()
                ->all(),
        ];
    }

    private function dateOnly(string $date): string
    {
        if ($date === '-') {
            return '';
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }
}
