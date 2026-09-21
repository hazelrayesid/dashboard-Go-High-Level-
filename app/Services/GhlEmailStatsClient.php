<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

class GhlEmailStatsClient
{
    private float $deadline = 0.0;

    public function startBudget(int $seconds): void
    {
        $this->deadline = microtime(true) + max($seconds, 2);
    }

    public function listWorkflows(?string $status): array
    {
        return $this->listCampaigns('workflows', $status);
    }

    public function campaignStats(string $source, string $sourceId, ?string $subSourceId = null): array
    {
        $query = $subSourceId ? ['subSourceId' => $subSourceId] : [];
        $response = $this->get('/emails/locations/'.rawurlencode((string) config('services.ghl.location_id')).'/campaigns/stats/'.rawurlencode($source).'/'.rawurlencode($sourceId), $query);

        if (! $response['ok']) {
            return ['ok' => false, 'stats' => [], 'error' => $response['error']];
        }

        return [
            'ok' => true,
            'stats' => Arr::get($response, 'data.stats', []),
            'error' => null,
        ];
    }

    public function hasTime(): bool
    {
        return $this->deadline === 0.0 || microtime(true) < $this->deadline;
    }

    private function listCampaigns(string $campaignPath, ?string $status): array
    {
        $campaigns = [];
        $offset = 0;
        $limit = 20;
        $pages = 0;
        $maxPages = max((int) config('services.ghl.email_stats_max_pages'), 1);

        do {
            if (! $this->hasTime() || $pages >= $maxPages) {
                break;
            }

            $query = array_filter([
                'limit' => $limit,
                'offset' => $offset,
                'status' => $status,
            ], fn (mixed $value): bool => $value !== null);

            $response = $this->get('/emails/locations/'.rawurlencode((string) config('services.ghl.location_id')).'/campaigns/'.$campaignPath, $query);

            if (! $response['ok']) {
                return ['ok' => false, 'campaigns' => [], 'error' => $response['error']];
            }

            $pageCampaigns = collect(Arr::get($response, 'data.campaigns', []))
                ->filter(fn (mixed $campaign): bool => is_array($campaign) && filled(Arr::get($campaign, 'id')))
                ->values()
                ->all();

            $campaigns = array_merge($campaigns, $pageCampaigns);
            $offset += $limit;
            $pages++;
            $total = (int) Arr::get($response, 'data.total', 0);
        } while ($offset < $total);

        return ['ok' => true, 'campaigns' => $campaigns, 'error' => null];
    }

    private function get(string $path, array $query = []): array
    {
        if (! $this->hasTime()) {
            return ['ok' => false, 'data' => [], 'error' => 'HighLevel email statistics timed out before this request could start.'];
        }

        try {
            $request = Http::baseUrl((string) config('services.ghl.base_url'))
                ->acceptJson()
                ->withToken((string) config('services.ghl.email_stats_access_token'))
                ->withHeaders(['Version' => (string) config('services.ghl.email_stats_version')])
                ->connectTimeout(min(max((int) config('services.ghl.email_stats_timeout'), 1), 3))
                ->retry(0, 250, throw: false)
                ->timeout(max((int) config('services.ghl.email_stats_timeout'), 1));

            /** @var Response $response */
            $response = $request->get($path, $query);
        } catch (ConnectionException) {
            return ['ok' => false, 'data' => [], 'error' => 'Unable to connect to HighLevel email statistics.'];
        } catch (\Throwable) {
            return ['ok' => false, 'data' => [], 'error' => 'HighLevel email statistics are temporarily unavailable.'];
        }

        if ($response->failed()) {
            return ['ok' => false, 'data' => [], 'error' => $this->safeErrorMessage($response->status())];
        }

        $data = $response->json();

        return ['ok' => true, 'data' => is_array($data) ? $data : [], 'error' => null];
    }

    private function safeErrorMessage(int $status): string
    {
        return match ($status) {
            401 => 'HighLevel rejected GHL_EMAIL_STATS_ACCESS_TOKEN.',
            403 => 'The email stats token does not have enough scope for this location.',
            429 => 'HighLevel is temporarily rate limiting email stats requests.',
            default => 'HighLevel returned an email statistics error.',
        };
    }
}
