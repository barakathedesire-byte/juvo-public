<?php

namespace App\Services\Store;

use App\Models\Coupon;
use App\Services\Pricing\PricingCalculationService;

/**
 * Backwards-compatible checkout facade over the authoritative pricing service.
 *
 * All pricing now lives in {@see PricingCalculationService}; this class only
 * preserves the historical `quote()` / `allocate()` API used by the cart,
 * checkout and order pipeline so there is a single source of truth.
 */
class CheckoutPricingService
{
    public function __construct(
        protected PricingCalculationService $pricing,
    ) {}

    /**
     * @param  array<int,array{unit_price:float|string,quantity:int,product?:object}>  $lines
     * @return array<string, mixed>
     */
    public function quote(array $lines, ?Coupon $coupon = null, ?string $region = null, ?string $country = null, array $options = []): array
    {
        $quote = $this->pricing->calculate($lines, array_merge($options, [
            'coupon' => $coupon,
            'region' => $region,
            'country' => $country ?? 'Tanzania',
        ]));

        // Keep the historical coupon_code semantics: only present when usable.
        $quote['coupon_code'] = $quote['coupon_error'] === null && $coupon ? $coupon->code : null;

        return $quote;
    }

    /**
     * Split order-wide discount/shipping/tax across per-vendor subtotals
     * proportionally, giving any rounding remainder to the last group so the
     * allocations always sum to the original total.
     *
     * @param  array<int,float>  $vendorSubtotals  keyed by vendor id
     * @return array<int,array{discount:float,shipping:float,tax:float}>
     */
    public function allocate(array $vendorSubtotals, float $discount, float $shipping, float $tax): array
    {
        $keys = array_keys($vendorSubtotals);
        $count = count($keys);
        $sum = array_sum($vendorSubtotals);

        $result = [];
        $running = ['discount' => 0.0, 'shipping' => 0.0, 'tax' => 0.0];
        $totals = ['discount' => $discount, 'shipping' => $shipping, 'tax' => $tax];

        foreach ($keys as $i => $key) {
            $share = $sum > 0 ? ($vendorSubtotals[$key] / $sum) : ($count > 0 ? 1 / $count : 0);
            $isLast = $i === $count - 1;

            foreach ($totals as $field => $amount) {
                $value = $isLast
                    ? round($amount - $running[$field], 2)
                    : round($amount * $share, 2);
                $result[$key][$field] = $value;
                $running[$field] += $value;
            }
        }

        return $result;
    }
}
