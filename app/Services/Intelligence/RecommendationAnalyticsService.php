<?php

namespace App\Services\Intelligence;

use App\Models\RecommendationEvent;

/**
 * Aggregates the recommendation event stream into the metrics the Algorithm
 * Engine surfaces: exposure, CTR, add-to-cart rate, attributed purchases,
 * conversion and revenue — broken down by surface and algorithm version.
 */
class RecommendationAnalyticsService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function bySurface(\DateTimeInterface $from, \DateTimeInterface $to, ?string $surface = null): array
    {
        $rows = RecommendationEvent::valid()
            ->whereBetween('occurred_at', [$from, $to])
            ->when($surface, fn ($q) => $q->where('surface', $surface))
            ->selectRaw("surface,
                algorithm_key,
                algorithm_version,
                SUM(type = 'impression') as impressions,
                SUM(type = 'click') as clicks,
                SUM(type = 'add_to_cart') as add_to_carts,
                SUM(type = 'purchase') as purchases,
                SUM(CASE WHEN type = 'purchase' THEN value_minor ELSE 0 END) as revenue_minor,
                COUNT(DISTINCT session_id) as sessions")
            ->groupBy('surface', 'algorithm_key', 'algorithm_version')
            ->get();

        return $rows->map(fn ($r) => $this->decorate($r->getAttributes()))->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function totals(\DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $r = RecommendationEvent::valid()
            ->whereBetween('occurred_at', [$from, $to])
            ->selectRaw("SUM(type = 'impression') as impressions,
                SUM(type = 'click') as clicks,
                SUM(type = 'add_to_cart') as add_to_carts,
                SUM(type = 'purchase') as purchases,
                SUM(CASE WHEN type = 'purchase' THEN value_minor ELSE 0 END) as revenue_minor,
                COUNT(DISTINCT session_id) as sessions")
            ->first();

        return $this->decorate((array) ($r ? $r->getAttributes() : []));
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function decorate(array $row): array
    {
        $impressions = (int) ($row['impressions'] ?? 0);
        $clicks = (int) ($row['clicks'] ?? 0);
        $atc = (int) ($row['add_to_carts'] ?? 0);
        $purchases = (int) ($row['purchases'] ?? 0);

        return array_merge($row, [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'add_to_carts' => $atc,
            'purchases' => $purchases,
            'revenue_minor' => (int) ($row['revenue_minor'] ?? 0),
            'sessions' => (int) ($row['sessions'] ?? 0),
            'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0.0,
            'atc_rate' => $impressions > 0 ? round($atc / $impressions, 4) : 0.0,
            'conversion_rate' => $clicks > 0 ? round($purchases / $clicks, 4) : 0.0,
        ]);
    }

    /**
     * Freshness: how recently events flowed per surface.
     *
     * @return array<int, array<string, mixed>>
     */
    public function freshness(int $limit = 12): array
    {
        return RecommendationEvent::valid()
            ->selectRaw('surface, MAX(occurred_at) as last_event_at, COUNT(*) as events')
            ->groupBy('surface')
            ->orderByDesc('last_event_at')
            ->limit($limit)
            ->get()
            ->map(fn ($r) => [
                'surface' => $r->surface,
                'last_event_at' => $r->last_event_at,
                'events' => (int) $r->events,
            ])
            ->all();
    }
}
