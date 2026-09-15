<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class GhlContactDateRange
{
    /**
     * @param  array<string, mixed>  $contact
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     */
    public function matches(array $contact, array $dateRange): bool
    {
        if (blank($dateRange['from'] ?? null) && blank($dateRange['to'] ?? null)) {
            return true;
        }

        $date = $this->dateOnly((string) (Arr::get($contact, 'dateAdded')
            ?? Arr::get($contact, 'createdAt')
            ?? Arr::get($contact, 'created')
            ?? ''));

        if ($date === '') {
            return false;
        }

        return (blank($dateRange['from'] ?? null) || $date >= $dateRange['from'])
            && (blank($dateRange['to'] ?? null) || $date <= $dateRange['to']);
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     * @return array<int, array{field: string, operator: string, value: array<string, string>}>
     */
    public function filters(array $dateRange): array
    {
        if (blank($dateRange['from'] ?? null) && blank($dateRange['to'] ?? null)) {
            return [];
        }

        $value = [];

        if (filled($dateRange['from'] ?? null)) {
            $from = Carbon::parse($dateRange['from']);
            $value['gte'] = str_contains((string) $dateRange['from'], 'T')
                ? $from->toISOString()
                : $from->startOfDay()->toISOString();
        }

        if (filled($dateRange['to'] ?? null)) {
            $to = Carbon::parse($dateRange['to']);
            $value['lte'] = str_contains((string) $dateRange['to'], 'T')
                ? $to->toISOString()
                : $to->endOfDay()->toISOString();
        }

        return [
            [
                'field' => 'dateAdded',
                'operator' => 'range',
                'value' => $value,
            ],
        ];
    }

    public function dateOnly(string $date): string
    {
        if ($date === '' || $date === '-') {
            return '';
        }

        try {
            return Carbon::parse($date)->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }
}
