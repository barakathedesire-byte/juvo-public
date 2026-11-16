<?php

namespace App\Services\Features;

use App\Models\Feature;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Central feature registry. Feature keys are resolved from the `features`
 * table and cached, so gating a code path is a single lookup:
 *
 *     if (feature_enabled('sales_attribution')) { ... }
 *
 * Unknown keys are disabled (fail closed).
 */
class FeatureFlagService
{
    public const CACHE_KEY = 'features.registry';

    /**
     * @return array<string, bool>
     */
    public function map(): array
    {
        if (! $this->tableReady()) {
            return [];
        }

        return Cache::rememberForever(self::CACHE_KEY, function () {
            return Feature::query()->pluck('is_enabled', 'key')
                ->map(fn ($enabled) => (bool) $enabled)
                ->all();
        });
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->map()[$key] ?? false);
    }

    public function enabledFor(string $key, int|string|null $subjectId = null): bool
    {
        if (! $this->tableReady()) {
            return false;
        }

        $feature = Feature::where('key', $key)->first();

        return $feature?->enabledFor($subjectId) ?? false;
    }

    public function setEnabled(Feature $feature, bool $enabled, ?int $actorId = null): Feature
    {
        $feature->update([
            'is_enabled' => $enabled,
            'updated_by' => $actorId,
        ]);

        $this->flush();

        return $feature;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected function tableReady(): bool
    {
        return Schema::hasTable('features');
    }
}
