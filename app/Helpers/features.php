<?php

use App\Services\Features\FeatureFlagService;

if (! function_exists('feature_enabled')) {
    /**
     * Whether a feature flag is enabled. Unknown keys fail closed.
     */
    function feature_enabled(string $key): bool
    {
        try {
            return app(FeatureFlagService::class)->isEnabled($key);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
