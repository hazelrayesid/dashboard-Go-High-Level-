<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GhlClient
{
    /**
     * GHL enforces a burst limit of 100 requests per 10 seconds per token.
     * A small margin keeps concurrent sync pages clear of 429s and the
     * multi-second backoff they would trigger.
     */
    private const BurstLimit = 80;
    private const BurstWindowSeconds = 10;

    /** @var array<int, float> Send times of recent requests, shared across instances in this process. */
    private static array $recentRequests = [];

    public function __construct(private readonly GhlContactDateRange $dateRange) {}

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
    ): array
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
        ], $this->dateRange->filters($dateRange)), $limit, $timeout, $page, $useCache);
    }

    /**
     * Run several tag searches concurrently. Each request is keyed by the
     * caller and may target its own page and date range. Requests that fail
     * with a retryable status fall back to the sequential path with backoff,
     * so a burst of 429s never loses a page.
     *
     * @param  array<int|string, array{page?: int, dateRange?: array{from?: string|null, to?: string|null}}>  $requests
     * @return array<int|string, array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}>
     */
    public function contactsByTagBatch(string $tag, int $limit, array $requests, ?int $timeout = null): array
    {
        if ($requests === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            return array_map(fn (): array => [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ], $requests);
        }

        $filters = [];
        foreach ($requests as $key => $request) {
            $filters[$key] = array_merge([
                [
                    'field' => 'tags',
                    'operator' => 'eq',
                    'value' => $tag,
                ],
            ], $this->dateRange->filters($request['dateRange'] ?? []));
        }

        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);
        $requestTimeout = min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);
        $retryAttempts = $requestTimeout >= 5 ? 2 : 1;

        $this->reserveBurstSlots(count($requests));

        $responses = Http::pool(function (Pool $pool) use ($requests, $filters, $limit, $requestTimeout, $retryAttempts): void {
            foreach ($requests as $key => $request) {
                $pool->as((string) $key)
                    ->baseUrl((string) config('services.ghl.base_url'))
                    ->acceptJson()
                    ->asJson()
                    ->withToken((string) config('services.ghl.access_token'))
                    ->withHeaders([
                        'Version' => (string) config('services.ghl.version'),
                    ])
                    ->connectTimeout(min(3, $requestTimeout))
                    ->retry($retryAttempts, 250, throw: false)
                    ->timeout($requestTimeout)
                    ->post('/contacts/search', $this->searchPayload($filters[$key], $limit, $request['page'] ?? 1));
            }
        });

        $results = [];
        foreach ($requests as $key => $request) {
            $response = $responses[(string) $key] ?? null;

            if ($response instanceof Response && ! $response->failed()) {
                $data = $response->json();
                $results[$key] = [
                    'ok' => true,
                    'status' => $response->status(),
                    'data' => is_array($data) ? $data : [],
                    'error' => null,
                ];

                continue;
            }

            if ($response instanceof Response && ! $this->shouldRetry($response->status())) {
                $results[$key] = [
                    'ok' => false,
                    'status' => $response->status(),
                    'data' => null,
                    'error' => $this->safeErrorMessage($response->status()),
                ];

                continue;
            }

            // Connection failure or retryable status: the sequential path sleeps between attempts.
            $results[$key] = $this->contactsByFilters($filters[$key], $limit, $timeout, $request['page'] ?? 1, false);
        }

        return $results;
    }

    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters
     * @return array<string, mixed>
     */
    private function searchPayload(array $filters, int $limit, int $page): array
    {
        return [
            'locationId' => config('services.ghl.location_id'),
            'page' => max($page, 1),
            'pageLimit' => min(max($limit, 1), 100),
            'filters' => $filters,
        ];
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    public function contactById(string $contactId, ?int $timeout = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ];
        }

        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);
        $requestTimeout = min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);

        try {
            $response = Http::baseUrl((string) config('services.ghl.base_url'))
                ->acceptJson()
                ->withToken((string) config('services.ghl.access_token'))
                ->withHeaders([
                    'Version' => (string) config('services.ghl.version'),
                ])
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
        ], $this->dateRange->filters($dateRange)), 1, $timeout);

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
            ], $this->dateRange->filters($dateRange)), $pageLimit, $timeout, $page);
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

    /**
     * @param  array<int, array{field: string, operator: string, value: mixed}>  $filters
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}
     */
    private function contactsByFilters(
        array $filters,
        int $limit = 25,
        ?int $timeout = null,
        int $page = 1,
        bool $useCache = true,
    ): array
    {
        if (! $this->isConfigured()) {
            return [
                'ok' => false,
                'status' => null,
                'data' => null,
                'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.',
            ];
        }

        $payload = $this->searchPayload($filters, $limit, $page);
        $cacheKey = $this->cacheKey($payload);
        if ($useCache && $cached = $this->cachedResponse($cacheKey, 60)) {
            return $cached;
        }

        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);
        $requestTimeout = min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);
        $retryAttempts = $requestTimeout >= 5 ? 2 : 1;

        $maxAttempts = 8;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->reserveBurstSlots(1);

            try {
                $response = Http::baseUrl((string) config('services.ghl.base_url'))
                    ->acceptJson()
                    ->asJson()
                    ->withToken((string) config('services.ghl.access_token'))
                    ->withHeaders([
                        'Version' => (string) config('services.ghl.version'),
                    ])
                    ->connectTimeout(min(3, $requestTimeout))
                    ->retry($retryAttempts, 250, throw: false)
                    ->timeout($requestTimeout)
                    ->post('/contacts/search', $payload);
            } catch (ConnectionException) {
                if ($attempt === $maxAttempts) {
                    return [
                        'ok' => false,
                        'status' => null,
                        'data' => null,
                        'error' => 'Unable to connect to HighLevel. Check the connection, base URL, or local firewall.',
                    ];
                }

                sleep($this->backoffSeconds($attempt));
                continue;
            }

            if (! $response->failed()) {
                break;
            }

            if (! $this->shouldRetry($response->status()) || $attempt === $maxAttempts) {
                if ($useCache && $this->shouldUseCachedResponse($response->status()) && $cached = $this->cachedResponse($cacheKey)) {
                    return $cached;
                }

                return [
                    'ok' => false,
                    'status' => $response->status(),
                    'data' => null,
                    'error' => $this->safeErrorMessage($response->status()),
                ];
            }

            sleep($this->retryAfterSeconds($response, $attempt));
        }

        $data = $response->json();
        // Full-sync pages are never read back from cache; skipping the write keeps the cache table small.
        if ($useCache) {
            Cache::put($cacheKey, [
                'stored_at' => time(),
                'status' => $response->status(),
                'data' => is_array($data) ? $data : [],
            ], now()->addMinutes(5));
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'data' => is_array($data) ? $data : [],
            'error' => null,
        ];
    }

    /**
     * Block until $count requests fit inside the burst window, then record them.
     */
    private function reserveBurstSlots(int $count): void
    {
        $count = min(max($count, 1), self::BurstLimit);

        while (true) {
            $windowStart = microtime(true) - self::BurstWindowSeconds;
            self::$recentRequests = array_values(array_filter(
                self::$recentRequests,
                fn (float $sentAt): bool => $sentAt > $windowStart,
            ));

            if (count(self::$recentRequests) + $count <= self::BurstLimit) {
                break;
            }

            // Sleep until the oldest request in the window expires.
            usleep((int) max(50_000, (self::$recentRequests[0] - $windowStart) * 1_000_000));
        }

        $now = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            self::$recentRequests[] = $now;
        }
    }

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

    private function cacheKey(array $payload): string
    {
        return 'ghl:contacts-search:'.hash('sha256', json_encode([
            'base_url' => config('services.ghl.base_url'),
            'version' => config('services.ghl.version'),
            'payload' => $payload,
        ]));
    }

    /**
     * @return array{ok: bool, status: int|null, data: array<string, mixed>|null, error: string|null}|null
     */
    private function cachedResponse(string $cacheKey, ?int $maxAge = null): ?array
    {
        $cached = Cache::get($cacheKey);

        if (! is_array($cached)) {
            return null;
        }

        if ($maxAge !== null && time() - (int) Arr::get($cached, 'stored_at', 0) > $maxAge) {
            return null;
        }

        return [
            'ok' => true,
            'status' => Arr::get($cached, 'status'),
            'data' => Arr::get($cached, 'data', []),
            'error' => null,
        ];
    }

    private function shouldUseCachedResponse(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    private function shouldRetry(int $status): bool
    {
        return $status === 408 || $status === 429 || $status >= 500;
    }

    private function backoffSeconds(int $attempt): int
    {
        return min(60, max(1, 2 ** min($attempt, 6)));
    }

    private function retryAfterSeconds(mixed $response, int $attempt): int
    {
        $header = (int) $response->header('Retry-After', 0);

        return min(120, max($header, $this->backoffSeconds($attempt)));
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
