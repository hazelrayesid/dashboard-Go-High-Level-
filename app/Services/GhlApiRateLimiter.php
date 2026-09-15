<?php

namespace App\Services;

class GhlApiRateLimiter
{
    /**
     * GHL enforces a burst limit of 100 requests per 10 seconds per token.
     * A small margin keeps concurrent sync pages clear of 429s.
     */
    private const BurstLimit = 80;
    private const BurstWindowSeconds = 10;

    /** @var array<int, float> Send times of recent requests, shared across instances in this process. */
    private static array $recentRequests = [];

    public function reserve(int $count): void
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

            usleep((int) max(50_000, (self::$recentRequests[0] - $windowStart) * 1_000_000));
        }

        $now = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            self::$recentRequests[] = $now;
        }
    }
}
