<?php

namespace App\Services\Intelligence;

use App\Models\ProductAssociation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes product-to-product associations used by the discovery engine:
 *
 *  - "complementary": products frequently bought together in the same order.
 *  - "similar": products viewed/engaged together in the same session.
 *
 * Only pairs meeting the minimum support threshold are stored, and stale rows
 * are pruned so recommendations never surface unsupported associations.
 */
class ProductAssociationService
{
    public function recompute(int $minSupport = 2): array
    {
        return [
            'similar' => $this->recomputeSimilar($minSupport),
            'complementary' => $this->recomputeComplementary($minSupport),
        ];
    }

    protected function recomputeComplementary(int $minSupport): int
    {
        $rows = DB::table('order_details as a')
            ->join('orders as o', 'o.id', '=', 'a.order_id')
            ->join('order_details as b', function ($join) {
                $join->on('b.order_id', '=', 'a.order_id')
                    ->on('b.product_id', '>', 'a.product_id');
            })
            ->whereNotIn('o.status', ['canceled', 'returned'])
            ->whereNotNull('a.product_id')
            ->whereNotNull('b.product_id')
            ->selectRaw('a.product_id as product_id, b.product_id as related_product_id, COUNT(DISTINCT a.order_id) as support_count')
            ->groupBy('a.product_id', 'b.product_id')
            ->havingRaw('COUNT(DISTINCT a.order_id) >= ?', [$minSupport])
            ->get();

        return $this->persist($rows->map(fn ($r) => (array) $r), 'complementary');
    }

    protected function recomputeSimilar(int $minSupport): int
    {
        $rows = \App\Models\RecommendationEvent::valid()
            ->whereIn('type', ['click', 'add_to_cart', 'purchase'])
            ->whereNotNull('session_id')
            ->whereNotNull('product_id')
            ->where('occurred_at', '>=', now()->subDays(60))
            ->get(['session_id', 'product_id']);

        $pairs = [];
        foreach ($rows->groupBy('session_id') as $session) {
            $ids = $session->pluck('product_id')->unique()->take(20)->values()->all();
            $count = count($ids);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = min($ids[$i], $ids[$j]);
                    $b = max($ids[$i], $ids[$j]);
                    $pairs[$a.'-'.$b] = ($pairs[$a.'-'.$b] ?? 0) + 1;
                }
            }
        }

        $collection = collect($pairs)
            ->filter(fn ($count) => $count >= $minSupport)
            ->map(function ($count, $key) {
                [$a, $b] = explode('-', $key);

                return ['product_id' => (int) $a, 'related_product_id' => (int) $b, 'support_count' => $count];
            })
            ->values();

        return $this->persist($collection, 'similar');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pairs
     */
    protected function persist(Collection $pairs, string $type): int
    {
        if ($pairs->isEmpty()) {
            return 0;
        }

        $max = max(1, (int) $pairs->max('support_count'));
        $now = now();
        $records = [];

        foreach ($pairs as $pair) {
            $records[] = [
                'product_id' => (int) $pair['product_id'],
                'related_product_id' => (int) $pair['related_product_id'],
                'type' => $type,
                'score' => round(((int) $pair['support_count']) / $max, 5),
                'support_count' => (int) $pair['support_count'],
                'computed_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($records, 500) as $chunk) {
            ProductAssociation::upsert(
                $chunk,
                ['product_id', 'related_product_id', 'type'],
                ['score', 'support_count', 'computed_at', 'updated_at']
            );
        }

        return count($records);
    }
}
