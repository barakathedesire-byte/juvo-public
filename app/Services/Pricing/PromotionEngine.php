<?php

namespace App\Services\Pricing;

use App\Models\BusinessRule;
use App\Models\Coupon;
use App\Models\Promotion;
use App\Support\Money;

/**
 * The single reusable discount/promotion evaluation engine.
 *
 * It turns eligible promotion rules and an optional coupon into explicit,
 * funding-aware discount lines. It never mutates prices and never spends
 * budget — it only decides what *would* apply and who funds it. Stacking,
 * per-order caps and global percent caps are all applied here so checkout,
 * admin previews and recommendations agree by construction.
 */
class PromotionEngine
{
    public const FUNDING_VENDOR = 'vendor';

    public const FUNDING_JUVO = 'juvo';

    public const FUNDING_JOINT = 'joint';

    /**
     * @param  array<int, array{product?:object, unit_price:float|string, quantity?:int}>  $lines
     * @return array{
     *     discounts: array<int, array<string, mixed>>,
     *     total: float,
     *     juvo_funded: float,
     *     vendor_funded: float,
     *     coupon_code: ?string,
     *     coupon_error: ?string,
     *     promotions: array<int, int>,
     *     explanation: array<int, string>
     * }
     */
    public function evaluate(array $lines, ?Coupon $coupon = null, array $context = []): array
    {
        $subtotal = Money::of(0);
        $lineTotals = [];
        foreach ($lines as $i => $line) {
            $amount = Money::of($line['unit_price'] ?? 0)->mul(max(1, (int) ($line['quantity'] ?? 1)));
            $lineTotals[$i] = $amount;
            $subtotal = $subtotal->add($amount);
        }

        $discounts = [];
        $explanation = [];
        $promotionIds = [];

        [$couponLine, $couponError] = $this->couponLine($coupon, $lines, $lineTotals, $subtotal);
        if ($couponLine) {
            $discounts[] = $couponLine;
        }

        $couponActive = $couponLine !== null;

        foreach ($this->candidatePromotions($couponActive) as $promotion) {
            $line = $this->promotionLine($promotion, $lines, $lineTotals, $subtotal);
            if ($line !== null) {
                $discounts[] = $line;
                $promotionIds[] = (int) $promotion->id;
            }
        }

        $discounts = $this->applyStacking($discounts);

        $juvo = Money::of(0);
        $vendor = Money::of(0);
        $total = Money::of(0);
        foreach ($discounts as $discount) {
            $amount = Money::of($discount['amount']);
            $total = $total->add($amount);
            if ($discount['funding_source'] === self::FUNDING_JUVO) {
                $juvo = $juvo->add($amount);
            } elseif ($discount['funding_source'] === self::FUNDING_JOINT) {
                $half = Money::fromMinor((int) round($amount->minor / 2));
                $juvo = $juvo->add($half);
                $vendor = $vendor->add($amount->sub($half));
            } else {
                $vendor = $vendor->add($amount);
            }
            $explanation[] = $discount['label'];
        }

        // Discount can never exceed the order subtotal.
        if ($total->minor > $subtotal->minor) {
            $total = $subtotal;
        }

        return [
            'discounts' => array_values($discounts),
            'total' => $total->toFloat(),
            'juvo_funded' => $juvo->toFloat(),
            'vendor_funded' => $vendor->toFloat(),
            'coupon_code' => $couponLine['code'] ?? null,
            'coupon_error' => $couponError,
            'promotions' => array_values(array_unique($promotionIds)),
            'explanation' => $explanation,
        ];
    }

    /**
     * @return array{0:?array<string,mixed>,1:?string}
     */
    protected function couponLine(?Coupon $coupon, array $lines, array $lineTotals, Money $subtotal): array
    {
        if (! $coupon) {
            return [null, null];
        }

        $base = $subtotal;
        if ($coupon->isVendorScoped()) {
            $base = Money::of(0);
            foreach ($lines as $i => $line) {
                if ((int) ($line['product']->vendor_id ?? null) === (int) $coupon->vendor_id) {
                    $base = $base->add($lineTotals[$i]);
                }
            }
            if ($base->minor <= 0) {
                return [null, 'This coupon applies only to items from a specific store.'];
            }
        }

        $reason = $coupon->rejectionReason($base->toFloat());
        if ($reason !== null) {
            return [null, $reason];
        }

        $discount = Money::of($coupon->discountFor($base->toFloat()));
        if ($coupon->type === 'percentage') {
            $maxPercent = (float) BusinessRule::number('max_discount_percent', 0);
            if ($maxPercent > 0) {
                $discount = $discount->min($base->percent($maxPercent));
            }
        }
        $discount = $discount->min($subtotal);

        if ($discount->minor <= 0) {
            return [null, null];
        }

        return [[
            'kind' => 'coupon',
            'code' => $coupon->code,
            'label' => 'Coupon '.$coupon->code,
            'group' => 'coupon:'.$coupon->id,
            'amount' => $discount->toFloat(),
            'funding_source' => $this->couponFundingSource($coupon),
            'vendor_id' => $coupon->vendor_id ? (int) $coupon->vendor_id : null,
        ], null];
    }

    protected function couponFundingSource(Coupon $coupon): string
    {
        if (! empty($coupon->funding_source)) {
            return $coupon->funding_source;
        }

        // Vendor coupons are paid by the vendor; platform-wide coupons by Juvo.
        return $coupon->isVendorScoped() ? self::FUNDING_VENDOR : self::FUNDING_JUVO;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Promotion>
     */
    protected function candidatePromotions(bool $couponActive): \Illuminate\Support\Collection
    {
        return Promotion::query()
            ->whereIn('scope', ['order', 'vendor', 'category'])
            ->where(function ($q) {
                $q->where('active', true)
                    ->orWhereIn('status', ['active', 'scheduled']);
            })
            ->get()
            ->filter(fn (Promotion $p) => $p->isRunning() || ($p->status === 'scheduled' && $p->hasStarted() && ! $p->isEnded()))
            ->filter(fn (Promotion $p) => ! $couponActive || $p->coupon_compatible)
            ->sortByDesc(fn (Promotion $p) => [(int) $p->priority, $p->id])
            ->values();
    }

    protected function promotionLine(Promotion $promotion, array $lines, array $lineTotals, Money $subtotal): ?array
    {
        if ($promotion->isExhausted()) {
            return null;
        }

        $base = match ($promotion->scope) {
            'order' => $subtotal,
            'vendor' => $this->scopedSubtotal($lines, $lineTotals, fn ($line) => (int) ($line['product']->vendor_id ?? null) === (int) $promotion->vendor_id),
            'category' => $this->scopedSubtotal($lines, $lineTotals, fn ($line) => (int) ($line['product']->category_id ?? null) === (int) $promotion->category_id),
            default => Money::of(0),
        };

        if ($base->minor <= 0) {
            return null;
        }

        $discount = $promotion->type === 'percent'
            ? $base->percent((float) $promotion->value)
            : Money::of($promotion->value);

        if ($promotion->per_order_cap !== null) {
            $discount = $discount->min(Money::of($promotion->per_order_cap));
        }

        $maxPercent = (float) BusinessRule::number('max_discount_percent', 0);
        if ($maxPercent > 0) {
            $discount = $discount->min($subtotal->percent($maxPercent));
        }

        $remaining = $promotion->remainingBudget();
        if ($promotion->fundsByJuvo() && $remaining !== null) {
            $discount = $discount->min(Money::of($remaining));
        }

        $discount = $discount->min($subtotal);
        if ($discount->minor <= 0) {
            return null;
        }

        return [
            'kind' => 'promotion',
            'promotion_id' => (int) $promotion->id,
            'label' => $promotion->title ?: ('Promotion #'.$promotion->id),
            'group' => $promotion->stack_group ?: 'promotion:'.$promotion->id,
            'amount' => $discount->toFloat(),
            'funding_source' => $promotion->funding_source ?? self::FUNDING_VENDOR,
            'vendor_id' => $promotion->vendor_id ? (int) $promotion->vendor_id : null,
            'scope' => $promotion->scope,
        ];
    }

    protected function scopedSubtotal(array $lines, array $lineTotals, callable $match): Money
    {
        $sum = Money::of(0);
        foreach ($lines as $i => $line) {
            if ($match($line)) {
                $sum = $sum->add($lineTotals[$i]);
            }
        }

        return $sum;
    }

    /**
     * Collapse discounts within the same stack group to the best single
     * discount, unless the platform is explicitly configured to sum them.
     *
     * @param  array<int, array<string, mixed>>  $discounts
     * @return array<int, array<string, mixed>>
     */
    protected function applyStacking(array $discounts): array
    {
        $mode = BusinessRule::get('pricing.stack_mode', 'best_per_group');

        $groups = [];
        foreach ($discounts as $discount) {
            $groups[$discount['group']][] = $discount;
        }

        $result = [];
        foreach ($groups as $group) {
            if ($mode === 'sum' || count($group) === 1) {
                foreach ($group as $discount) {
                    $result[] = $discount;
                }

                continue;
            }

            usort($group, fn ($a, $b) => $b['amount'] <=> $a['amount']);
            $result[] = $group[0];
        }

        return $result;
    }
}
