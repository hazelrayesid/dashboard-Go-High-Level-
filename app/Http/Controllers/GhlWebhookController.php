<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessGhlContactWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GhlWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->hasValidSecret($request)) {
            return response()->json(['message' => 'Unauthorized webhook.'], 401);
        }

        ProcessGhlContactWebhook::dispatch($request->all())->onQueue('ghl-sync');

        return response()->json(['message' => 'GHL webhook accepted.']);
    }

    private function hasValidSecret(Request $request): bool
    {
        $secret = (string) config('services.ghl.webhook_secret');

        if ($secret === '') {
            return true;
        }

        $incoming = (string) ($request->header('X-GHL-Webhook-Secret')
            ?? $request->header('X-Webhook-Secret')
            ?? $request->query('secret', ''));

        return hash_equals($secret, $incoming);
    }
}
