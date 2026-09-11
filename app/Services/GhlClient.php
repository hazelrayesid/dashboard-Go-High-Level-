<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
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
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN dan GHL_LOCATION_ID harus diset di environment.',
            ];
        }

        return $this->contactsByFilters([
            [
                'field' => 'tags',
                'operator' => 'eq',
                'value' => $tag,
            ],
        ], $limit, $timeout);
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactsByTagWithEmptyAuditReportUrl(string $tag, int $limit = 25, ?int $timeout = null): array
    {
        $tagResult = $this->contactsByTag($tag, 1, $timeout);

        if (! $tagResult['ok']) {
            return $tagResult;
        }

        $nonEmptyResult = $this->contactsByFilters([
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
        ], 1, $timeout);

        if (! $nonEmptyResult['ok']) {
            return $nonEmptyResult;
        }

        $sampleResult = $this->emptyAuditReportUrlSamplesByTag($tag, $limit, $timeout);

        if (! $sampleResult['ok']) {
            return $sampleResult;
        }

        $tagTotal = (int) Arr::get($tagResult, 'data.total', 0);
        $nonEmptyTotal = (int) Arr::get($nonEmptyResult, 'data.total', 0);
        $emptyTotal = max($tagTotal - $nonEmptyTotal, 0);
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
    private function emptyAuditReportUrlSamplesByTag(string $tag, int $limit, ?int $timeout = null): array
    {
        $contacts = [];
        $status = null;
        $page = 1;
        $pageLimit = 100;
        $maxPages = 5;

        while (count($contacts) < $limit && $page <= $maxPages) {
            $result = $this->contactsByFilters([
                [
                    'field' => 'tags',
                    'operator' => 'eq',
                    'value' => $tag,
                ],
            ], $pageLimit, $timeout, $page);
            $status ??= $result['status'];

            if (! $result['ok']) {
                return $result;
            }

            $pageContacts = collect(Arr::get($result, 'data.contacts', []))
                ->filter(fn (mixed $contact): bool => is_array($contact) && $this->hasEmptyAuditReportUrl($contact))
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
                'error' => 'GHL_ACCESS_TOKEN dan GHL_LOCATION_ID harus diset di environment.',
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
                'error' => 'Tidak bisa terhubung ke HighLevel. Cek koneksi, base URL, atau firewall lokal.',
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

    private function safeErrorMessage(int $status): string
    {
        return match ($status) {
            401 => 'Token ditolak oleh HighLevel. Rotasi token jika ragu, lalu update GHL_ACCESS_TOKEN.',
            403 => 'Token valid, tapi scope belum cukup untuk membaca contacts atau locationId tidak cocok.',
            429 => 'HighLevel membatasi request sementara. Coba lagi nanti.',
            default => 'HighLevel mengembalikan error. Detail respons disembunyikan agar token/data sensitif tidak bocor.',
        };
    }
}
