<?php

use App\Services\Ads\AdDeliveryService;

if (! function_exists('ad_slot')) {
    /**
     * Fetch servable ad campaigns for a placement key.
     */
    function ad_slot(string $key, int $limit = 1)
    {
        try {
            return app(AdDeliveryService::class)->forPlacement($key, $limit);
        } catch (\Throwable $e) {
            return collect();
        }
    }
}
