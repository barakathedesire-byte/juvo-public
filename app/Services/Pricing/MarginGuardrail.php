<?php

namespace App\Services\Pricing;

use App\Models\BusinessRule;
use App\Models\Vendor;
use App\Support\Money;

/**
 * Deterministic margin / discount guardrails.
 *
 * Guardrails separate *warnings* from *hard blocks*. A block can only be
 * overridden by an authorised role, and every override is audited. When vendor
 * cost data is unavailable we never invent a margin: we fall back to the safe,
 * known signals (list price, discount percent and an absolute price floor) and
 * say so in the returned labels.
 */
class MarginGuardrail
{
    /**
     * @return array{
     *     level: string,
     *     allowed: bool,
     *     requires_approval: bool,
     *     discount_percent: float,
     *     min_price: ?float,
     *     messages: array<int, string>,
     *     labels: array<int, string>
     * }
     */
    public function evaluate(?Vendor $vendor, float $basePrice, float $proposedPrice, array $context = []): array
    {
        $basePrice = round($basePrice, 2);
        $proposedPrice = round($proposedPrice, 2);
        $messages = [];
        $labels = [];

        $maxDiscount = (float) BusinessRule::number('pricing.max_discount_percent', (float) BusinessRule::number('max_discount_percent', 0));
        $warnDiscount = (float) BusinessRule::number('pricing.warn_discount_percent', $maxDiscount > 0 ? $maxDiscount * 0.8 : 0);
        $minPrice = null;
        $minPriceRule = BusinessRule::number('pricing.min_price', 0);
        if ($minPriceRule > 0) {
            $minPrice = (float) $minPriceRule;
        }
        if ($vendor && $vendor->meta && isset($vendor->meta['min_price'])) {
            $minPrice = (float) $vendor->meta['min_price'];
        }

        $discountPercent = 0.0;
        if ($basePrice > 0) {
            $discountPercent = round(max(0, ($basePrice - $proposedPrice) / $basePrice) * 100, 2);
        }

        $level = 'ok';
        $requiresApproval = false;

        if ($proposedPrice <= 0 && $basePrice > 0) {
            $level = 'block';
            $messages[] = 'Selling price must be greater than zero.';
        }

        if ($minPrice !== null && $proposedPrice > 0 && $proposedPrice < $minPrice) {
            $level = 'block';
            $messages[] = 'Selling price is below the floor of '.number_format($minPrice, 2).'.';
        }

        if ($maxDiscount > 0 && $discountPercent > $maxDiscount) {
            $level = 'block';
            $requiresApproval = true;
            $messages[] = 'Discount of '.$discountPercent.'% exceeds the maximum allowed '.$maxDiscount.'%.';
        } elseif ($warnDiscount > 0 && $discountPercent > $warnDiscount) {
            if ($level === 'ok') {
                $level = 'warning';
            }
            $messages[] = 'Discount of '.$discountPercent.'% is above the warning threshold of '.$warnDiscount.'%.';
        }

        // Margin is only meaningful when a cost basis exists. Without one we do
        // not fabricate a margin figure — we label the basis instead.
        $cost = $context['cost'] ?? ($vendor && $vendor->meta ? ($vendor->meta['default_cost'] ?? null) : null);
        if ($cost !== null && (float) $cost > 0) {
            $marginPercent = round((($proposedPrice - (float) $cost) / $proposedPrice) * 100, 2);
            $labels[] = 'margin_basis=declared_cost';
            $minMargin = (float) BusinessRule::number('pricing.min_margin_percent', 0);
            if ($minMargin > 0 && $marginPercent < $minMargin) {
                if ($level === 'ok') {
                    $level = 'warning';
                }
                $messages[] = 'Margin of '.$marginPercent.'% is below the '.$minMargin.'% guardrail.';
            }
        } else {
            $labels[] = 'margin_basis=price_and_discount';
        }

        return [
            'level' => $level,
            'allowed' => $level !== 'block',
            'requires_approval' => $requiresApproval,
            'discount_percent' => $discountPercent,
            'min_price' => $minPrice,
            'messages' => $messages,
            'labels' => $labels,
        ];
    }

    /**
     * Enforce the floor as an absolute amount for a proposed price.
     */
    public function floorPrice(?Vendor $vendor): ?float
    {
        $rule = BusinessRule::number('pricing.min_price', 0);
        if ($rule > 0) {
            return (float) $rule;
        }

        if ($vendor && $vendor->meta && isset($vendor->meta['min_price'])) {
            return (float) $vendor->meta['min_price'];
        }

        return null;
    }

    public function money(float $value): Money
    {
        return Money::of($value);
    }
}
