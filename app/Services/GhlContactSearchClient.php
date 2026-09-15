<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GhlContactSearchClient
{
    public function __construct(
        private readonly GhlContactDateRange $dateRange,
        private readonly GhlApiRateLimiter $rateLimiter,
    ) {}

    public function byTagPage(
        string $tag,
        int $limit = 25,
        ?int $timeout = null,
        int $page = 1,
        array $dateRange = [],
        bool $useCache = true,
    ): array {
        return $this->byFilters(
            array_merge($this->tagFilter($tag), $this->dateRange->filters($dateRange)),
            $limit,
            $timeout,
            $page,
            $useCache,
        );
    }

    public function byTagBatch(string $tag, int $limit, array $requests, ?int $timeout = null): array
    {
        if ($requests === []) {
            return [];
        }

        if (! $this->isConfigured()) {
            return array_map(fn (): array => $this->configurationError(), $requests);
        }

        $filters = [];
        foreach ($requests as $key => $request) {
            $filters[$key] = array_merge($this->tagFilter($tag), $this->dateRange->filters($request['dateRange'] ?? []));
        }

        $requestTimeout = $this->requestTimeout($timeout);
        $retryAttempts = $requestTimeout >= 5 ? 2 : 1;

        $this->rateLimiter->reserve(count($requests));

        $responses = Http::pool(function (Pool $pool) use ($requests, $filters, $limit, $requestTimeout, $retryAttempts): void {
            foreach ($requests as $key => $request) {
                $pool->as((string) $key)
                    ->baseUrl((string) config('services.ghl.base_url'))
                    ->acceptJson()
                    ->asJson()
                    ->withToken((string) config('services.ghl.access_token'))
                    ->withHeaders(['Version' => (string) config('services.ghl.version')])
                    ->connectTimeout(min(3, $requestTimeout))
                    ->retry($retryAttempts, 250, throw: false)
                    ->timeout($requestTimeout)
                    ->post('/contacts/search', $this->payload($filters[$key], $limit, $request['page'] ?? 1));
            }
        });

        return $this->batchResults($requests, $responses, $filters, $limit, $timeout);
    }

    public function byFilters(
        array $filters,
        int $limit = 25,
        ?int $timeout = null,
        int $page = 1,
        bool $useCache = true,
    ): array {
        if (! $this->isConfigured()) {
            return $this->configurationError();
        }

        $payload = $this->payload($filters, $limit, $page);
        $cacheKey = $this->cacheKey($payload);
        if ($useCache && $cached = $this->cachedResponse($cacheKey, 60)) {
            return $cached;
        }

        $requestTimeout = $this->requestTimeout($timeout);
        $response = $this->sendSearchWithRetry($payload, $requestTimeout, $cacheKey, $useCache);

        if (! $response['ok']) {
            return $response;
        }

        if ($useCache) {
            Cache::put($cacheKey, [
                'stored_at' => time(),
                'status' => $response['status'],
                'data' => $response['data'],
            ], now()->addMinutes(5));
        }

        return $response;
    }

    private function sendSearchWithRetry(array $payload, int $requestTimeout, string $cacheKey, bool $useCache): array
    {
        $retryAttempts = $requestTimeout >= 5 ? 2 : 1;
        $maxAttempts = 8;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $this->rateLimiter->reserve(1);

            try {
                $response = Http::baseUrl((string) config('services.ghl.base_url'))
                    ->acceptJson()
                    ->asJson()
                    ->withToken((string) config('services.ghl.access_token'))
                    ->withHeaders(['Version' => (string) config('services.ghl.version')])
                    ->connectTimeout(min(3, $requestTimeout))
                    ->retry($retryAttempts, 250, throw: false)
                    ->timeout($requestTimeout)
                    ->post('/contacts/search', $payload);
            } catch (ConnectionException) {
                if ($attempt === $maxAttempts) {
                    return $this->connectionError();
                }

                sleep($this->backoffSeconds($attempt));
                continue;
            }

            if (! $response->failed()) {
                return $this->successfulResponse($response);
            }

            if (! $this->shouldRetry($response->status()) || $attempt === $maxAttempts) {
                if ($useCache && $this->shouldUseCachedResponse($response->status()) && $cached = $this->cachedResponse($cacheKey)) {
                    return $cached;
                }

                return $this->statusError($response->status());
            }

            sleep($this->retryAfterSeconds($response, $attempt));
        }

        return $this->connectionError();
    }

    private function batchResults(array $requests, array $responses, array $filters, int $limit, ?int $timeout): array
    {
        $results = [];

        foreach ($requests as $key => $request) {
            $response = $responses[(string) $key] ?? null;

            if ($response instanceof Response && ! $response->failed()) {
                $results[$key] = $this->successfulResponse($response);

                continue;
            }

            if ($response instanceof Response && ! $this->shouldRetry($response->status())) {
                $results[$key] = $this->statusError($response->status());

                continue;
            }

            $results[$key] = $this->byFilters($filters[$key], $limit, $timeout, $request['page'] ?? 1, false);
        }

        return $results;
    }

    private function payload(array $filters, int $limit, int $page): array
    {
        return [
            'locationId' => config('services.ghl.location_id'),
            'page' => max($page, 1),
            'pageLimit' => min(max($limit, 1), 100),
            'filters' => $filters,
        ];
    }

    private function tagFilter(string $tag): array
    {
        return [['field' => 'tags', 'operator' => 'eq', 'value' => $tag]];
    }

    private function successfulResponse(Response $response): array
    {
        $data = $response->json();

        return [
            'ok' => true,
            'status' => $response->status(),
            'data' => is_array($data) ? $data : [],
            'error' => null,
        ];
    }

    private function cacheKey(array $payload): string
    {
        return 'ghl:contacts-search:'.hash('sha256', json_encode([
            'base_url' => config('services.ghl.base_url'),
            'version' => config('services.ghl.version'),
            'payload' => $payload,
        ]));
    }

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

    private function requestTimeout(?int $timeout): int
    {
        $configuredTimeout = max((int) config('services.ghl.timeout'), 1);

        return min(max($timeout ?? $configuredTimeout, 1), $configuredTimeout);
    }

    private function isConfigured(): bool
    {
        return filled(config('services.ghl.access_token')) && filled(config('services.ghl.location_id'));
    }

    private function configurationError(): array
    {
        return ['ok' => false, 'status' => null, 'data' => null, 'error' => 'GHL_ACCESS_TOKEN and GHL_LOCATION_ID must be set in the environment.'];
    }

    private function connectionError(): array
    {
        return ['ok' => false, 'status' => null, 'data' => null, 'error' => 'Unable to connect to HighLevel. Check the connection, base URL, or local firewall.'];
    }

    private function statusError(int $status): array
    {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $this->safeErrorMessage($status)];
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
