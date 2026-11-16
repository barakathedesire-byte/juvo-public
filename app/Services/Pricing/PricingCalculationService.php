<?php

namespace App\Services\Pricing;

use App\Models\BusinessRule;
use App\Models\ShippingRate;
use App\Models\TaxRate;
use App\Services\Delivery\DeliveryCalculator;
use App\Support\Money;

/**
 * The single authoritative pricing calculation for the whole platform.
 *
 * Everything that needs a price — storefront, cart, checkout, order creation,
 * admin previews — must call this service so there is exactly one source of
 * truth. It produces an explicit, funding-aware breakdown: who funds each
 * discount, the per-line allocation, shipping, tax and a persisted snapshot.
 */
class PricingCalculationService
{
    public function __construct(
        protected PromotionEngine $promotions,
        protected DeliveryCalculator $delivery,
    ) {}

    /**
     * @param  array<int, array{product?:object, unit_price:float|string, quantity?:int}>  $lines
     * @return array<string, mixed>
     */
    public function calculate(array $lines, array $options = []): array
    {
        $coupon = $options['coupon'] ?? null;
        $region = $options['region'] ?? null;
        $country = $options['country'] ?? 'Tanzania';

        $subtotal = Money::of(0);
        $lineTotals = [];
        foreach ($lines as $i => $line) {
            $amount = Money::of($line['unit_price'] ?? 0)->mul(max(1, (int) ($line['quantity'] ?? 1)));
            $lineTotals[$i] = $amount;
            $subtotal = $subtotal->add($amount);
        }

        $evaluation = $this->promotions->evaluate($lines, $coupon, $options);
        $discount = Money::of($evaluation['total']);
        $discountedSubtotal = $subtotal->sub($discount)->max(Money::of(0));

        $shipping = $this->resolveShipping($region, $country, $subtotal, $options);
        $taxRate = TaxRate::resolve($region, $country);
        $tax = $taxRate ? $discountedSubtotal->percent((float) $taxRate->rate) : Money::of(0);
        $total = $discountedSubtotal->add($shipping)->add($tax);

        $lineBreakdown = $this->allocateDiscountToLines($lines, $lineTotals, $discount, $evaluation['discounts']);

        return [
            'subtotal' => $subtotal->toFloat(),
            'discount' => $discount->toFloat(),
            'discounted_subtotal' => $discountedSubtotal->toFloat(),
            'shipping' => $shipping->toFloat(),
            'tax' => $tax->toFloat(),
            'total' => $total->toFloat(),
            'coupon_code' => $evaluation['coupon_code'],
            'coupon_error' => $evaluation['coupon_error'],
            'juvo_funded' => $evaluation['juvo_funded'],
            'vendor_funded' => $evaluation['vendor_funded'],
            'discounts' => $evaluation['discounts'],
            'promotions' => $evaluation['promotions'],
            'explanation' => $evaluation['explanation'],
            'line_breakdown' => $lineBreakdown,
            'shipping_rate' => $this->lastShippingRate,
            'tax_rate' => $taxRate,
            'free_shipping_threshold' => (float) BusinessRule::number('free_shipping_threshold', 0),
            'shipping_label' => $this->lastShippingLabel ?? $this->lastShippingRate?->eta_label,
            'fulfillment' => $options['fulfillment'] ?? 'delivery',
            'pickup_location' => $options['pickup_location'] ?? null,
            'currency' => $options['currency'] ?? getWebConfig('default_currency', 'TZS'),
        ];
    }

    /**
     * Persistable, human-auditable snapshot of a single order's pricing.
     */
    public function snapshot(array $quote): array
    {
        return [
            'subtotal' => $quote['subtotal'],
            'discount' => $quote['discount'],
            'juvo_funded_discount' => $quote['juvo_funded'],
            'vendor_funded_discount' => $quote['vendor_funded'],
            'shipping' => $quote['shipping'],
            'tax' => $quote['tax'],
            'total' => $quote['total'],
            'coupon_code' => $quote['coupon_code'],
            'discounts' => $quote['discounts'],
            'explanation' => $quote['explanation'],
            'currency' => $quote['currency'] ?? null,
            'captured_at' => now()->toIso8601String(),
        ];
    }

    protected ?ShippingRate $lastShippingRate = null;

    protected ?string $lastShippingLabel = null;

    protected function resolveShipping(?string $region, ?string $country, Money $subtotal, array $options = []): Money
    {
        $this->lastShippingRate = null;
        $this->lastShippingLabel = null;

        if (($options['fulfillment'] ?? 'delivery') === 'pickup') {
            $pickup = $this->delivery->calculatePickupFee((string) $region, (string) ($options['pickup_location'] ?? ''));

            $this->lastShippingLabel = $pickup['eta'] ?? 'Pickup';

            return Money::of((float) ($pickup['fee'] ?? 0));
        }

        $freeThreshold = (float) BusinessRule::number('free_shipping_threshold', 0);
        if ($freeThreshold > 0 && $subtotal->toFloat() >= $freeThreshold) {
            $this->lastShippingLabel = 'Free shipping';

            return Money::of(0);
        }

        $location = $options['location'] ?? null;
        if ($location) {
            $door = $this->delivery->calculateDoorFee((string) $region, (string) $location, $subtotal->toFloat());

            if (! empty($door['available'])) {
                $this->lastShippingLabel = $door['eta'] ?? 'Door delivery';

                return Money::of((float) ($door['fee'] ?? 0));
            }
        }

        $this->lastShippingRate = ShippingRate::resolve($region, $subtotal->toFloat(), $country);

        if ($this->lastShippingRate) {
            return Money::of($this->lastShippingRate->costFor($subtotal->toFloat()));
        }

        return Money::of(0);
    }

    /**
     * Distribute an order-wide discount across lines proportionally and record
     * which funding sources contributed to each line.
     *
     * @param  array<int, array<string, mixed>>  $discounts
     * @return array<int, array<string, mixed>>
     */
    protected function allocateDiscountToLines(array $lines, array $lineTotals, Money $discount, array $discounts): array
    {
        $weights = array_map(fn (Money $m) => $m->minor, $lineTotals);
        $allocated = $discount->allocate($weights);

        $breakdown = [];
        foreach ($lines as $i => $line) {
            $lineDiscount = $allocated[$i] ?? 0.0;
            $breakdown[] = [
                'product_id' => $line['product']->id ?? null,
                'variant_id' => $line['variant']->id ?? null,
                'vendor_id' => $line['product']->vendor_id ?? null,
                'quantity' => max(1, (int) ($line['quantity'] ?? 1)),
                'unit_price' => round((float) $line['unit_price'], 2),
                'line_total' => $lineTotals[$i]->toFloat(),
                'discount' => $lineDiscount,
                'line_payable' => round($lineTotals[$i]->toFloat() - $lineDiscount, 2),
                'funding' => $this->fundingSplit($discounts, $lineDiscount, $lineTotals[$i]),
            ];
        }

        return $breakdown;
    }

    /**
     * Attribute a per-line discount amount to funding sources using the
     * order's discount lines proportionally.
     *
     * @param  array<int, array<string, mixed>>  $discounts
     * @return array{vendor:float,juvo:float}
     */
    protected function fundingSplit(array $discounts, float $lineDiscount, Money $lineTotal): array
    {
        if ($lineDiscount <= 0 || $discounts === []) {
            return ['vendor' => 0.0, 'juvo' => 0.0];
        }

        $total = array_sum(array_map(fn ($d) => (float) $d['amount'], $discounts));
        if ($total > 0) {
            $juvoAmount = 0.0;
            foreach ($discounts as $discount) {
                if (in_array($discount['funding_source'], ['juvo', 'joint'], true)) {
                    $juvoAmount += (float) $discount['amount'];
                }
            }
            $juvo = round($lineDiscount * ($juvoAmount / $total), 2);
            $vendor = round($lineDiscount - $juvo, 2);

            return ['vendor' => $vendor, 'juvo' => $juvo];
        }

        return ['vendor' => $lineDiscount, 'juvo' => 0.0];
    }
}
