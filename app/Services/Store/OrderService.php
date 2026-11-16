<?php

namespace App\Services\Store;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\SalesRep;
use App\Models\ShippingAddress;
use App\Notifications\OrderPlacedNotification;
use App\Notifications\VendorNewOrderNotification;
use App\Services\Pricing\BudgetService;
use App\Services\Sales\AgentAttributionService;
use App\Services\Vendor\CommissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OrderService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PaymentService $payments,
        protected CheckoutPricingService $pricing,
        protected CommissionService $commissions,
        protected BudgetService $budgets
    ) {}

    /**
     * Build normalized line items from the server-side cart.
     *
     * @return array<int,array{product:Product,variant:ProductVariant,quantity:int,unit_price:float}>
     */
    public function cartLines(): array
    {
        $lines = [];

        foreach (session('cart', []) as $item) {
            $product = Product::visible()->find($item['product_id'] ?? null);
            if (! $product) {
                continue;
            }

            $variant = ! empty($item['variant_id'])
                ? ProductVariant::where('product_id', $product->id)->find($item['variant_id'])
                : ProductVariant::where('product_id', $product->id)->where('is_primary', true)->first();

            if (! $variant) {
                continue;
            }

            $lines[] = [
                'product' => $product,
                'variant' => $variant,
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => (float) ($variant->discount_price ?? $variant->price),
            ];
        }

        return $lines;
    }

    /**
     * The coupon currently held in the cart session (model or code).
     */
    public function sessionCoupon(): ?Coupon
    {
        $coupon = session('cart_coupon');

        if ($coupon instanceof Coupon) {
            return Coupon::find($coupon->id);
        }

        if (is_string($coupon) && $coupon !== '') {
            return Coupon::where('code', $coupon)->first();
        }

        return null;
    }

    /**
     * Create per-vendor orders from priced line items, applying coupon,
     * shipping and tax and allocating them proportionally across vendors.
     *
     * @param  array  $context  customer_id, guest_email, is_guest, payment_method,
     *                          status, payment_status, payment_meta, region, country, notify
     * @return Collection<int,Order>
     */
    public function createOrders(array $lines, array $shippingData, ?Coupon $coupon, array $context = []): Collection
    {
        $region = $context['region'] ?? null;
        $country = $context['country'] ?? 'Tanzania';

        $quote = $this->pricing->quote($lines, $coupon, $region, $country, [
            'fulfillment' => $context['fulfillment_type'] ?? 'delivery',
            'pickup_location' => $context['pickup_location_name'] ?? null,
        ]);

        $vendorSubtotals = [];
        $groups = [];
        foreach ($lines as $line) {
            $vendorId = $line['product']->vendor_id ?? 0;
            $vendorSubtotals[$vendorId] = ($vendorSubtotals[$vendorId] ?? 0) + $line['unit_price'] * $line['quantity'];
            $groups[$vendorId][] = $line;
        }

        $allocations = $this->pricing->allocate(
            $vendorSubtotals,
            $quote['discount'],
            $quote['shipping'],
            $quote['tax']
        );

        if ($coupon && $coupon->isVendorScoped() && $quote['discount'] > 0) {
            foreach ($allocations as $vendorId => $alloc) {
                $allocations[$vendorId]['discount'] = 0.0;
            }
            $target = (int) $coupon->vendor_id;
            if (isset($allocations[$target])) {
                $allocations[$target]['discount'] = round(min($quote['discount'], $vendorSubtotals[$target]), 2);
            }
        }

        // Funding attribution per vendor (who pays for the discount).
        $funding = [];
        $discountTotal = (float) $quote['discount'];
        foreach ($allocations as $vendorId => $alloc) {
            $share = $discountTotal > 0 ? ($alloc['discount'] / $discountTotal) : 0.0;
            $funding[$vendorId] = [
                'juvo' => round((float) $quote['juvo_funded'] * $share, 2),
                'vendor' => round((float) $quote['vendor_funded'] * $share, 2),
            ];
        }

        $lineIndex = collect($quote['line_breakdown'] ?? [])
            ->keyBy(fn ($l) => ($l['product_id'] ?? 0).':'.($l['variant_id'] ?? 0));

        $region = $context['region'] ?? null;

        $orders = DB::transaction(function () use ($groups, $allocations, $funding, $lineIndex, $quote, $coupon, $shippingData, $context, $country, $region) {
            $created = collect();

            foreach ($groups as $vendorId => $rows) {
                $subtotal = round(array_sum(array_map(
                    fn ($row) => $row['unit_price'] * $row['quantity'],
                    $rows
                )), 2);

                $alloc = $allocations[$vendorId] ?? ['discount' => 0, 'shipping' => 0, 'tax' => 0];
                $total = round($subtotal - $alloc['discount'] + $alloc['shipping'] + $alloc['tax'], 2);
                $vendorFunding = $funding[$vendorId] ?? ['juvo' => 0.0, 'vendor' => 0.0];

                $order = Order::create([
                    'vendor_id' => $vendorId ?: null,
                    'customer_id' => $context['customer_id'] ?? null,
                    'guest_email' => $context['guest_email'] ?? null,
                    'is_guest' => (bool) ($context['is_guest'] ?? false),
                    'subtotal' => $subtotal,
                    'discount_amount' => $alloc['discount'],
                    'juvo_funded_discount' => $vendorFunding['juvo'],
                    'vendor_funded_discount' => $vendorFunding['vendor'],
                    'coupon_code' => $alloc['discount'] > 0 && $coupon ? $coupon->code : null,
                    'delivery_fee' => $alloc['shipping'],
                    'tax_amount' => $alloc['tax'],
                    'total_amount' => $total,
                    'pricing_breakdown' => [
                        'subtotal' => $subtotal,
                        'discount' => $alloc['discount'],
                        'juvo_funded' => $vendorFunding['juvo'],
                        'vendor_funded' => $vendorFunding['vendor'],
                        'shipping' => $alloc['shipping'],
                        'tax' => $alloc['tax'],
                        'total' => $total,
                        'discounts' => $quote['discounts'] ?? [],
                        'explanation' => $quote['explanation'] ?? [],
                    ],
                    'status' => $context['status'] ?? 'pending',
                    'payment_status' => $context['payment_status'] ?? 'pending',
                    'payment_method' => $context['payment_method'] ?? null,
                    'fulfillment_type' => $context['fulfillment_type'] ?? 'delivery',
                    'pickup_location_id' => $context['pickup_location_id'] ?? null,
                    'pickup_location_name' => $context['pickup_location_name'] ?? null,
                    'currency' => $context['currency'] ?? getWebConfig('default_currency', 'TZS'),
                ]);

                foreach ($rows as $row) {
                    $key = $row['product']->id.':'.$row['variant']->id;
                    $line = $lineIndex->get($key);

                    OrderDetail::create([
                        'order_id' => $order->id,
                        'product_id' => $row['product']->id,
                        'variant_id' => $row['variant']->id,
                        'quantity' => $row['quantity'],
                        'price' => $row['unit_price'],
                        'discount_amount' => $line['discount'] ?? 0,
                        'funding_breakdown' => $line['funding'] ?? null,
                    ]);

                    $this->inventory->decrement($row['variant'], $row['quantity'], [
                        'reference_type' => Order::class,
                        'reference_id' => $order->id,
                        'reason' => 'Order #'.$order->id,
                        'created_by' => $context['customer_id'] ?? null,
                    ]);
                }

                if ($shippingData) {
                    ShippingAddress::create([
                        'order_id' => $order->id,
                        'customer_id' => $context['customer_id'] ?? null,
                        'name' => $shippingData['name'] ?? '',
                        'phone' => $shippingData['phone'] ?? '',
                        'address' => $shippingData['address'] ?? '',
                        'city' => $shippingData['city'] ?? '',
                        'state' => $shippingData['state'] ?? ($region ?: null),
                        'region_id' => $shippingData['region_id'] ?? null,
                        'location_id' => $shippingData['location_id'] ?? null,
                        'postal_code' => $shippingData['postal_code'] ?? '',
                        'country' => $shippingData['country'] ?? $country,
                    ]);
                }

                $this->payments->recordPending($order, $context['payment_method'] ?? null, $context['payment_meta'] ?? []);

                $this->commissions->recordForOrder($order);

                $created->push($order->fresh());
            }

            return $created;
        });

        // Side effects that must not run inside the transaction.
        $this->attributeAgentSales($orders);

        if ($coupon && $quote['discount'] > 0) {
            $coupon->increment('used_count');
        }

        $this->recordPromotionUsage($orders->first(), $quote, $allocations);

        if (($context['notify'] ?? true) === true) {
            $this->dispatchNotifications($orders, $context);
        }

        return $orders;
    }

    /**
     * Attribute freshly placed orders to the referring sales agent captured in
     * the session. Attribution is server-side only and never fatal to checkout.
     *
     * @param  Collection<int,Order>  $orders
     */
    protected function attributeAgentSales(Collection $orders): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $code = session('sales_ref_code');

        if (! $code) {
            return;
        }

        $agent = SalesRep::where('code', $code)->first();

        if (! $agent || ! $agent->canOperate()) {
            return;
        }

        try {
            foreach ($orders as $order) {
                app(AgentAttributionService::class)->attribute($order, $agent, 'referral');
            }
        } catch (\Throwable $e) {
            Log::warning('Agent attribution failed at checkout', [
                'code' => $code,
                'error' => $e->getMessage(),
            ]);
        }

        session()->forget(['sales_ref_code', 'sales_ref_'.$agent->id]);
    }

    /**
     * Record promotion redemptions and commit funded budgets for a checkout.
     *
     * @param  array<string, mixed>  $quote
     * @param  array<int, array<string, float>>  $allocations
     */
    protected function recordPromotionUsage(?Order $order, array $quote, array $allocations): void
    {
        if (! $order) {
            return;
        }

        foreach ($quote['discounts'] ?? [] as $discount) {
            if (($discount['kind'] ?? null) !== 'promotion') {
                continue;
            }

            $promotion = Promotion::find($discount['promotion_id'] ?? null);
            if (! $promotion) {
                continue;
            }

            $amount = (float) $discount['amount'];
            $reference = 'checkout:'.$order->id.':promotion:'.$promotion->id;

            PromotionRedemption::firstOrCreate(
                ['reference' => $reference],
                [
                    'promotion_id' => $promotion->id,
                    'order_id' => $order->id,
                    'discount_amount' => $amount,
                    'funding_source' => $promotion->funding_source,
                    'funded_vendor_id' => $promotion->funding_source === Promotion::FUNDING_VENDOR ? $promotion->vendor_id : null,
                ]
            );

            if ($promotion->fundsByJuvo() && $promotion->budget_amount !== null) {
                $reservation = $this->budgets->reserve($promotion, $amount, $reference, $order->id);
                if ($reservation) {
                    $this->budgets->commit($reference);
                }
            }
        }
    }

    /**
     * Notify the buyer and the vendors about orders that were created with
     * notifications suppressed (mobile money waits for the aggregator hand-off
     * before the order is announced).
     *
     * @param  Collection<int,Order>  $orders
     */
    public function notifyOrders(Collection $orders, array $context = []): void
    {
        if ($orders->isEmpty()) {
            return;
        }

        $this->dispatchNotifications($orders, $context);
    }

    /**
     * Send order-placed emails to buyer and new-order alerts to vendors.
     */
    protected function dispatchNotifications(Collection $orders, array $context): void
    {
        foreach ($orders as $order) {
            try {
                $customer = $order->customer_id ? \App\Models\Customer::find($order->customer_id) : null;

                if ($customer) {
                    $customer->notify(new OrderPlacedNotification($order));
                } elseif ($order->guest_email) {
                    Notification::route('mail', $order->guest_email)
                        ->notify(new OrderPlacedNotification($order));
                }

                if ($order->vendor_id && ($vendor = \App\Models\Vendor::find($order->vendor_id))) {
                    $vendor->notify(new VendorNewOrderNotification($order));
                }
            } catch (\Throwable $e) {
                Log::warning('Order notification failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $first = $orders->first();
        if ($first) {
            try {
                foreach (\App\Models\User::role(['super_admin', 'admin'])->get() as $admin) {
                    $admin->notify(new \App\Notifications\AdminNewOrderNotification($first));
                }
            } catch (\Throwable $e) {
                Log::warning('Admin order notification failed', [
                    'order_id' => $first->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Create order(s) from a completed PayPal capture, grouped per vendor.
     *
     * Prices and totals are recomputed from the server-side cart; the captured
     * PayPal amount is only used for reconciliation, never for pricing.
     *
     * @param  array  $paypalResult  The PayPal API capture result
     * @return Collection<int, Order>
     */
    public function createOrderFromPaypal(array $paypalResult): Collection
    {
        $payer = $paypalResult['payer'] ?? [];
        $capture = $paypalResult['purchase_units'][0]['payments']['captures'][0] ?? [];
        $transactionId = $capture['id'] ?? ($paypalResult['id'] ?? null);
        $customerId = Auth::guard('customer')->id();

        $lines = $this->cartLines();
        $shipping = session('checkout.shipping');

        $shippingData = $shipping ? [
            'name' => $shipping['name'] ?? '',
            'phone' => $shipping['phone'] ?? '',
            'address' => $shipping['address'] ?? '',
            'city' => $shipping['city'] ?? '',
            'state' => $shipping['state'] ?? null,
            'postal_code' => $shipping['postal_code'] ?? '',
            'country' => $shipping['country'] ?? 'Tanzania',
        ] : null;

        $orders = $this->createOrders($lines, $shippingData, $this->sessionCoupon(), [
            'customer_id' => $customerId,
            'guest_email' => $payer['email_address'] ?? null,
            'is_guest' => ! $customerId,
            'payment_method' => 'paypal',
            'status' => 'processing',
            'payment_status' => 'paid',
            'region' => $shipping['state'] ?? null,
            'fulfillment_type' => session('checkout_fulfillment', 'delivery'),
            'pickup_location_name' => session('checkout_pickup'),
            'payment_meta' => [
                'source' => 'paypal',
                'paypal_order_id' => $paypalResult['id'] ?? null,
            ],
        ]);

        foreach ($orders as $order) {
            $this->payments->markCompleted($order, $paypalResult, $transactionId);
        }

        session()->forget(['cart', 'cart_count', 'cart_coupon', 'checkout.shipping', 'checkout_fulfillment', 'checkout_pickup', 'checkout_region']);

        return $orders;
    }
}
