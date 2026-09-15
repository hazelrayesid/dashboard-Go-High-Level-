<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use RuntimeException;

class GhlContactSyncPlanner
{
    public const PageLimit = 100;
    public const MaxPagesPerDateRange = 100;
    public const ChunkPageBudget = 100;

    public function __construct(private readonly GhlClient $client) {}

    /**
     * @return array<int, array{from: string, to: string, total: int}>
     */
    public function dateRangesForTag(string $tag): array
    {
        return $this->splitDateRange(
            $tag,
            '1970-01-01T00:00:00.000Z',
            now()->addSecond()->toISOString(),
        );
    }

    /**
     * @param  array{ranges: array<int, array{from: string, to: string, total?: int}>, range_index: int, page: int}  $cursor
     * @return array<int, array{range_index: int, page: int}>
     */
    public function pagesToFetch(array $cursor, int $budget): array
    {
        $wanted = max(1, min($this->concurrency(), $budget));
        $fetches = [];
        $page = $cursor['page'];

        for ($rangeIndex = $cursor['range_index']; $rangeIndex < count($cursor['ranges']) && count($fetches) < $wanted; $rangeIndex++) {
            $lastPage = $this->lastPageOfRange($cursor['ranges'][$rangeIndex]);

            for (; $page <= $lastPage && count($fetches) < $wanted; $page++) {
                $fetches[] = ['range_index' => $rangeIndex, 'page' => $page];
            }

            if (! isset($cursor['ranges'][$rangeIndex]['total'])) {
                break;
            }

            $page = 1;
        }

        return $fetches;
    }

    /**
     * @param  array<int, array{from: string, to: string, total?: int}>  $ranges
     * @param  array<int, array{range_index: int, page: int}>  $fetches
     */
    public function fetchTagPages(string $tag, array $ranges, array $fetches): array
    {
        $requests = [];
        foreach ($fetches as $fetch) {
            $range = $ranges[$fetch['range_index']];
            $requests[$this->pageKey($fetch['range_index'], $fetch['page'])] = [
                'page' => $fetch['page'],
                'dateRange' => ['from' => $range['from'], 'to' => $range['to']],
            ];
        }

        return $this->client->contactsByTagBatch($tag, self::PageLimit, $requests);
    }

    public function pageKey(int $rangeIndex, int $page): string
    {
        return $rangeIndex.':'.$page;
    }

    /**
     * @return array<int, array{from: string, to: string, total: int}>
     */
    private function splitDateRange(string $tag, string $from, string $to): array
    {
        $nodes = [['from' => $from, 'to' => $to, 'total' => null]];

        while (($pending = array_keys(array_filter($nodes, fn (array $node): bool => $node['total'] === null))) !== []) {
            $requests = [];
            foreach ($pending as $index) {
                $requests[$index] = ['page' => 1, 'dateRange' => ['from' => $nodes[$index]['from'], 'to' => $nodes[$index]['to']]];
            }

            $results = $this->client->contactsByTagBatch($tag, 1, $requests);
            $nodes = $this->splitOversizedNodes($tag, $nodes, $results);
        }

        return $nodes;
    }

    private function splitOversizedNodes(string $tag, array $nodes, array $results): array
    {
        $next = [];

        foreach ($nodes as $index => $node) {
            if ($node['total'] !== null) {
                $next[] = $node;

                continue;
            }

            $result = $results[$index] ?? ['ok' => false, 'error' => null];
            if (! $result['ok']) {
                throw new RuntimeException($result['error'] ?? "Unable to count GHL contacts for tag [$tag].");
            }

            $total = (int) Arr::get($result, 'data.total', 0);
            if ($total <= self::PageLimit * self::MaxPagesPerDateRange) {
                if ($total > 0) {
                    $next[] = ['from' => $node['from'], 'to' => $node['to'], 'total' => $total];
                }

                continue;
            }

            array_push($next, ...$this->bisectNode($tag, $node));
        }

        return $next;
    }

    private function bisectNode(string $tag, array $node): array
    {
        $start = Carbon::parse($node['from']);
        $end = Carbon::parse($node['to']);

        if ($start->diffInMilliseconds($end) < 1) {
            throw new RuntimeException("GHL returned more than 10,000 contacts in a one-millisecond range for tag [$tag].");
        }

        $midpoint = $start->copy()->addMilliseconds((int) floor($start->diffInMilliseconds($end) / 2));

        return [
            ['from' => $node['from'], 'to' => $midpoint->toISOString(), 'total' => null],
            ['from' => $midpoint->copy()->addMillisecond()->toISOString(), 'to' => $node['to'], 'total' => null],
        ];
    }

    private function lastPageOfRange(array $dateRange): int
    {
        $total = (int) ($dateRange['total'] ?? 0);

        return $total > 0
            ? max(1, min(self::MaxPagesPerDateRange, (int) ceil($total / self::PageLimit)))
            : self::MaxPagesPerDateRange;
    }

    private function concurrency(): int
    {
        return max(1, (int) config('services.ghl.sync_concurrency', 10));
    }
}
