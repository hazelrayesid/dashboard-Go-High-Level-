<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GhlClient
{
    public function __construct(
        private readonly GhlContactDateRange $dateRange,
        private readonly GhlContactSearchClient $contacts,
    ) {}

    public function isConfigured(): bool
    {
        return filled(config('services.ghl.access_token')) && filled(config('services.ghl.location_id'));
    }

    public function contactsByTag(string $tag, int $limit = 25, ?int $timeout = null): array
    {
        return $this->contactsByTagPage($tag, $limit, $timeout);
    }

    public function contactsByTagPage(
        string $tag,
        int $limit = 25,
        ?int $timeout = null,
        int $page = 1,
        array $dateRange = [],
        bool $useCache = true,
    ): array {
        return $this->contacts->byTagPage($tag, $limit, $timeout, $page, $dateRange, $useCache);
    }

    /**
     * @param  array<int|string, array{page?: int, dateRange?: array{from?: string|null, to?: string|null}}>  $requests
     */
    public function contactsByTagBatch(string $tag, int $limit, array $requests, ?int $timeout = null): array
    {
        return $this->contacts->byTagBatch($tag, $limit, $requests, $timeout);
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactById(string $contactId, ?int $timeout = null): array
    {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $requestTimeout = $this->requestTimeout($timeout);

        try {
            $response = Http::baseUrl((string) config('services.ghl.base_url'))
                ->acceptJson()
                ->withToken((string) config('services.ghl.access_token'))
                ->withHeaders(['Version' => (string) config('services.ghl.version')])
                ->connectTimeout(min(3, $requestTimeout))
                ->retry($requestTimeout >= 5 ? 2 : 1, 250, throw: false)
                ->timeout($requestTimeout)
                ->get('/contacts/'.rawurlencode($contactId));
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

        $data = $response->json();

        return [
            'ok' => true,
            'status' => $response->status(),
            'data' => is_array($data) ? $data : [],
            'error' => null,
        ];
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

        $nonEmptyResult = $this->contacts->byFilters(array_merge([
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
        ], $this->dateRange->filters($dateRange)), 1, $timeout);

        if (! $nonEmptyResult['ok']) {
            return $nonEmptyResult;
        }

        $emptyTotal = max(
            (int) Arr::get($tagResult, 'data.total', 0) - (int) Arr::get($nonEmptyResult, 'data.total', 0),
            0,
        );

        if ($emptyTotal === 0) {
            return [
                'ok' => true,
                'status' => $tagResult['status'] ?? $nonEmptyResult['status'],
                'data' => ['contacts' => [], 'total' => 0],
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

    private function emptyAuditReportUrlSamplesByTag(string $tag, int $limit, ?int $timeout = null, array $dateRange = []): array
    {
        $contacts = [];
        $status = null;
        $page = 1;
        $pageLimit = 100;
        $maxPages = 5;

        while (count($contacts) < $limit && $page <= $maxPages) {
            $result = $this->contactsByTagPage($tag, $pageLimit, $timeout, $page, $dateRange);
            $status ??= $result['status'];

            if (! $result['ok']) {
                return $result;
            }

            $pageContacts = collect(Arr::get($result, 'data.contacts', []))
                ->filter(fn (mixed $contact): bool => is_array($contact)
                    && $this->hasEmptyAuditReportUrl($contact)
                    && $this->dateRange->matches($contact, $dateRange))
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

    private function hasEmptyAuditReportUrl(array $contact): bool
    {
        $fieldId = (string) config('services.ghl.audit_report_url_field_id');
        $field = collect(Arr::get($contact, 'customFields', []))
            ->first(fn (mixed $customField): bool => is_array($customField)
                && (string) Arr::get($customField, 'id') === $fieldId);

        return ! is_array($field) || blank(Arr::get($field, 'value'));
    }

    private function requestTimeout(?int $timeout): int
    {
        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);

        return min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);
    }

    private function configurationError(): array
    {
        return [
            'ok' => false,
            'status' => null,
            'data' => null,
            'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
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
