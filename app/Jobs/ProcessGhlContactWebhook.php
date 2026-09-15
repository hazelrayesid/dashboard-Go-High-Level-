<?php

namespace App\Jobs;

use App\Services\GhlContactWebhookService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessGhlContactWebhook implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    public function handle(GhlContactWebhookService $webhookService): void
    {
        $webhookService->handle($this->payload);
    }
}
