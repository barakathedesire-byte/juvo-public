<?php

namespace App\Jobs\Logistics;

use App\Services\Logistics\JuvoLogisticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessLogisticsWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;
    public int $timeout = 120;

    public function __construct(
        public array $payload
    ) {}

    public function handle(JuvoLogisticsService $logistics): void
    {
        Log::info('Processing Juvo Logistics webhook', [
            'event_type' => $this->payload['event_type'] ?? 'unknown',
            'tracking_id' => $this->payload['tracking_id'] ?? $this->payload['order_id'] ?? null,
        ]);

        try {
            $result = $this->logistics->processWebhook($this->payload);

            Log::info('Juvo Logistics webhook processed', [
                'result' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process Juvo Logistics webhook', [
                'error' => $e->getMessage(),
                'payload' => $this->payload,
            ]);
            throw $e; // Re-throw to trigger retry
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Juvo Logistics webhook job failed permanently', [
            'error' => $exception->getMessage(),
            'payload' => $this->payload,
        ]);
    }
}
