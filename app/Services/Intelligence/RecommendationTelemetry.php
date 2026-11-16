<?php

namespace App\Services\Intelligence;

use Illuminate\Support\Facades\DB;

/**
 * Lightweight render telemetry for discovery surfaces. Records how often a
 * surface rendered, how often it came back empty and how long it took, so the
 * intelligence dashboards can report empty-rate and latency alongside CTR.
 *
 * Telemetry must never break rendering, so every write is best-effort.
 */
class RecommendationTelemetry
{
    public function recordRender(string $surface, int $itemsServed, int $latencyMs): void
    {
        try {
            DB::table('recommendation_render_stats')->upsert(
                [[
                    'surface' => $surface,
                    'period_date' => now()->toDateString(),
                    'renders' => 1,
                    'empty_renders' => $itemsServed === 0 ? 1 : 0,
                    'items_served' => $itemsServed,
                    'total_latency_ms' => max(0, $latencyMs),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['surface', 'period_date'],
                [
                    'renders' => DB::raw('renders + 1'),
                    'empty_renders' => DB::raw('empty_renders + VALUES(empty_renders)'),
                    'items_served' => DB::raw('items_served + VALUES(items_served)'),
                    'total_latency_ms' => DB::raw('total_latency_ms + VALUES(total_latency_ms)'),
                    'updated_at' => now(),
                ]
            );
        } catch (\Throwable $e) {
            // Best-effort only.
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function daily(int $days = 14): array
    {
        return DB::table('recommendation_render_stats')
            ->where('period_date', '>=', now()->subDay($days)->toDateString())
            ->orderBy('period_date')
            ->get()
            ->map(fn ($row) => [
                'surface' => $row->surface,
                'period_date' => $row->period_date,
                'renders' => (int) $row->renders,
                'empty_renders' => (int) $row->empty_renders,
                'items_served' => (int) $row->items_served,
                'total_latency_ms' => (int) $row->total_latency_ms,
                'empty_rate' => $row->renders > 0 ? round($row->empty_renders / $row->renders, 4) : 0.0,
                'avg_latency_ms' => $row->renders > 0 ? round($row->total_latency_ms / $row->renders, 1) : 0.0,
            ])
            ->all();
    }
}
