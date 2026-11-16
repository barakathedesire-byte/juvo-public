<?php

namespace App\Http\Controllers\Api\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Logistics\JuvoLogisticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(protected JuvoLogisticsService $logistics) {}

    /**
     * Handle incoming webhook from Juvo Logistics.
     */
    public function handle(Request $request)
    {
        $allowedIps = config('juvo-logistics.webhook.allowed_ips', []);
        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('Juvo Logistics webhook rejected: IP not allowed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Forbidden'], 403);
        }

        $rawPayload = $request->getContent();
        $payload = json_decode($rawPayload, true);
        $signature = $request->header(
            config('juvo-logistics.webhook.signature_header', 'X-Juvo-Signature'),
            ''
        ) ?: $request->header('X-Signature', '');

        if (! is_array($payload)) {
            return response()->json(['error' => 'Invalid JSON payload'], 400);
        }

        // Verify webhook signature
        if (! $this->logistics->verifyWebhook($rawPayload, $signature)) {
            Log::warning('Juvo Logistics webhook: Invalid signature', [
                'ip' => $request->ip(),
            ]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Process webhook asynchronously
        \App\Jobs\Logistics\ProcessLogisticsWebhook::dispatch($payload);

        // Respond immediately
        return response()->json(['received' => true]);
    }
}
