<?php

namespace App\Services\Pricing;

use App\Models\BusinessRule;
use App\Models\Product;
use App\Models\Promotion;
use App\Support\Money;

/**
 * Rule-based, fully explainable pricing recommendations.
 *
 * Recommendations are advisory only. They never change a price, never spend
 * budget and never override a margin guardrail — they surface a reason and a
 * suggested action for a human (vendor or admin) to accept or ignore.
 */
class PricingRecommendationService
{
    public function __construct(protected MarginGuardrail $guardrails) {}

    /**
     * @return array<int, array{key:string,title:string,message:string,severity:string,advisory:bool,basis:string}>
     */
    public function forProduct(Product $product): array
    {
        $variant = $product->primaryVariant;
        if (! $variant) {
            return [];
        }

        $base = (float) $variant->price;
        $discount = $variant->discount_price !== null ? (float) $variant->discount_price : null;
        $recommendations = [];

        if ($discount !== null) {
            $check = $this->guardrails->evaluate($product->vendor, $base, $discount);
            foreach ($check['messages'] as $message) {
                $recommendations[] = [
                    'key' => 'guardrail',
                    'title' => $check['level'] === 'block' ? 'Discount breaches policy' : 'Discount nearing limit',
                    'message' => $message,
                    'severity' => $check['level'] === 'block' ? 'critical' : 'warning',
                    'advisory' => true,
                    'basis' => implode(',', $check['labels']),
                ];
            }
        }

        $hasActive = Promotion::where('product_id', $product->id)->running()->exists();
        if (! $hasActive && $discount === null && $base > 0) {
            $recommendations[] = [
                'key' => 'no_promotion',
                'title' => 'Consider a temporary promotion',
                'message' => 'This product has no active promotion. A capped, time-boxed discount could lift conversion.',
                'severity' => 'info',
                'advisory' => true,
                'basis' => 'no_active_promotion',
            ];
        }

        $maxDiscount = (float) BusinessRule::number('pricing.max_discount_percent', 0);
        if ($maxDiscount > 0 && $discount !== null && $base > 0) {
            $current = round((($base - $discount) / $base) * 100, 2);
            if ($current < $maxDiscount * 0.5) {
                $recommendations[] = [
                    'key' => 'headroom',
                    'title' => 'Promotion headroom available',
                    'message' => sprintf('Current discount is %.2f%%; policy allows up to %.2f%%.', $current, $maxDiscount),
                    'severity' => 'info',
                    'advisory' => true,
                    'basis' => 'policy_headroom',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Total funded-discount exposure for a promotion, for admin oversight.
     */
    public function exposure(Promotion $promotion): array
    {
        $committed = (float) $promotion->budget_spent;
        $budget = $promotion->budget_amount !== null ? (float) $promotion->budget_amount : null;

        return [
            'budget' => $budget,
            'spent' => round($committed, 2),
            'remaining' => $budget === null ? null : Money::fromMinor((int) round(($budget - $committed) * 100))->toFloat(),
            'funding_source' => $promotion->funding_source,
        ];
    }
}
