<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

class GhlClient
{
    public function isConfigured(): bool
    {
        return filled(config('services.ghl.access_token')) && filled(config('services.ghl.location_id'));
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactsByTag(string $tag, int $limit = 25, ?int $timeout = null): array
    {
        return $this->contactsByTagPage($tag, $limit, $timeout);
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactsByTagPage(string $tag, int $limit = 25, ?int $timeout = null, int $page = 1, array $dateRange = []): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ];
        }

        return $this->contactsByFilters(array_merge([
            [
                'field' => 'tags',
                'operator' => 'eq',
                'value' => $tag,
            ],
        ], $this->dateRangeFilters($dateRange)), $limit, $timeout, $page);
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactsByTagWithEmptyAuditReportUrl(string $tag, int $limit = 25, ?int $timeout = null, array $dateRange = []): array
    {
        $tagResult = $this->contactsByTagPage($tag, 1, $timeout, dateRange: $dateRange);

        if (! $tagResult['ok']) {
            return $tagResult;
        }

        $nonEmptyResult = $this->contactsByFilters(array_merge([
            [
                'field' => 'tags',
                'operator' => 'eq',
                'value' => $tag,
            ],
            [
                'field' => 'customFields.'.config('services.ghl.audit_report_url_field_id'),
                'operator' => 'not_eq',
                'value' => '',
            ],
        ], $this->dateRangeFilters($dateRange)), 1, $timeout);

        if (! $nonEmptyResult['ok']) {
            return $nonEmptyResult;
        }

        $tagTotal = (int) Arr::get($tagResult, 'data.total', 0);
        $nonEmptyTotal = (int) Arr::get($nonEmptyResult, 'data.total', 0);
        $emptyTotal = max($tagTotal - $nonEmptyTotal, 0);

        if ($emptyTotal === 0) {
            return [
                'ok' => true,
                'status' => $tagResult['status'] ?? $nonEmptyResult['status'],
                'data' => [
                    'contacts' => [],
                    'total' => 0,
                ],
                'error' => null,
            ];
        }

        $sampleResult = $this->emptyAuditReportUrlSamplesByTag($tag, $limit, $timeout, $dateRange);

        if (! $sampleResult['ok']) {
            return $sampleResult;
        }

        $sampleData = $sampleResult['data'] ?? ['contacts' => []];
        $sampleData['total'] = $emptyTotal;

        return [
            'ok' => true,
            'status' => $tagResult['status'] ?? $nonEmptyResult['status'] ?? $sampleResult['status'],
            'data' => $sampleData,
            'error' => null,
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    private function emptyAuditReportUrlSamplesByTag(string $tag, int $limit, ?int $timeout = null, array $dateRange = []): array
    {
        $contacts = [];
        $status = null;
        $page = 1;
        $pageLimit = 100;
        $maxPages = 5;

        while (count($contacts) < $limit && $page <= $maxPages) {
            $result = $this->contactsByFilters(array_merge([
                [
                    'field' => 'tags',
                    'operator' => 'eq',
                    'value' => $tag,
                ],
            ], $this->dateRangeFilters($dateRange)), $pageLimit, $timeout, $page);
            $status ??= $result['status'];

            if (! $result['ok']) {
                return $result;
            }

            $pageContacts = collect(Arr::get($result, 'data.contacts', []))
                ->filter(fn (mixed $contact): bool => is_array($contact)
                    && $this->hasEmptyAuditReportUrl($contact)
                    && $this->matchesDateRange($contact, $dateRange))
                ->all();
            $contacts = array_merge($contacts, $pageContacts);

            if (count(Arr::get($result, 'data.contacts', [])) < $pageLimit) {
                break;
            }

            $page++;
        }

        return [
            'ok' => true,
            'status' => $status,
            'data' => ['contacts' => array_slice($contacts, 0, $limit)],
            'error' => null,
        ];
    }

    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    private function contactsByFilters(array $filters, int $limit = 25, ?int $timeout = null, int $page = 1): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ];
        }

        $payload = [
            'locationId' => config('services.ghl.location_id'),
            'page' => max($page, 1),
            'pageLimit' => min(max($limit, 1), 100),
            'filters' => $filters,
        ];
        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);
        $requestTimeout = min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);

        try {
            $response = Http::baseUrl((string) config('services.ghl.base_url'))
                ->acceptJson()
                ->asJson()
                ->withToken((string) config('services.ghl.access_token'))
                ->withHeaders([
                    'Version' => (string) config('services.ghl.version'),
                ])
                ->connectTimeout(1)
                ->timeout($requestTimeout)
                ->post('/contacts/search', $payload);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'Unable to connect to HighLevel. Check the connection, base URL, or local firewall.',
            ];
        }

        if ($response->failed()) {
            return [
                'ok' => false,
                'status' => $response->status(),
                'data' => null,
                'error' => $this->safeErrorMessage($response->status()),
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'data' => $response->json(),
            'error' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function hasEmptyAuditReportUrl(array $contact): bool
    {
        $fieldId = (string) config('services.ghl.audit_report_url_field_id');
        $field = collect(Arr::get($contact, 'customFields', []))
            ->first(fn (mixed $customField): bool => is_array($customField)
                && (string) Arr::get($customField, 'id') === $fieldId);

        if (! is_array($field)) {
            return true;
        }

        return blank(Arr::get($field, 'value'));
    }

    /**
     * @param  array<string, mixed>  $contact
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     */
    private function matchesDateRange(array $contact, array $dateRange): bool
    {
        if (blank($dateRange['from'] ?? null) && blank($dateRange['to'] ?? null)) {
            return true;
        }

        $rawDate = (string) (Arr::get($contact, 'dateAdded')
            ?? Arr::get($contact, 'createdAt')
            ?? Arr::get($contact, 'created')
            ?? '');

        if ($rawDate === '') {
            return false;
        }

        try {
            $date = Carbon::parse($rawDate)->toDateString();
        } catch (\Throwable) {
            return false;
        }

        return (blank($dateRange['from'] ?? null) || $date >= $dateRange['from'])
            && (blank($dateRange['to'] ?? null) || $date <= $dateRange['to']);
    }

    /**
     * @param  array{from?: string|null, to?: string|null}  $dateRange
     * @return array<int, array{field: string, operator: string, value: array<string, string>}>
     */
    private function dateRangeFilters(array $dateRange): array
    {
        if (blank($dateRange['from'] ?? null) && blank($dateRange['to'] ?? null)) {
            return [];
        }

        $value = [];

        if (filled($dateRange['from'] ?? null)) {
            $value['gte'] = Carbon::parse($dateRange['from'])->startOfDay()->toISOString();
        }

        if (filled($dateRange['to'] ?? null)) {
            $value['lte'] = Carbon::parse($dateRange['to'])->endOfDay()->toISOString();
        }

        return [
            [
                'field' => 'dateAdded',
                'operator' => 'range',
                'value' => $value,
            ],
        ];
    }

    private function safeErrorMessage(int $status): string
    {
        return match ($status) {
            401 => 'HighLevel rejected the token. Rotate the token if needed, then update GHL_ACCESS_TOKEN.',
            403 => 'The token is valid, but it does not have enough scope to read contacts or the locationId does not match.',
            429 => 'HighLevel is temporarily rate limiting requests. Try again later.',
            default => 'HighLevel returned an error. Response details are hidden to avoid exposing sensitive token or data.',
        };
    }
}
