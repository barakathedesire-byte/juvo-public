<?php

namespace App\Http\Controllers\Store;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\BusinessRule;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Region;
use App\Services\PaymentGateway\PaymentManager;
use App\Services\Payments\MobileMoney;
use App\Services\Store\CheckoutPricingService;
use App\Services\Store\OrderService;
use App\Services\Store\PaymentMethodResolver;
use App\Services\Store\PaymentService;
use App\Services\Vendor\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class CheckoutController extends Controller
{
    public function __construct(
        protected OrderService $orders,
        protected CheckoutPricingService $pricing,
        protected PaymentMethodResolver $paymentMethods,
        protected PaymentService $payments,
        protected MobileMoney $mobileMoney
    ) {}

    public function index()
    {
        $cart = Session::get('cart', []);

        if (empty($cart)) {
            return redirect()->route('cart.view')->with('error', 'Your cart is empty.');
        }

        $lines = $this->orders->cartLines();

        if (empty($lines)) {
            return redirect()->route('cart.view')->with('error', 'Your cart is empty.');
        }

        $customer = Auth::guard('customer')->user();
        $regions = Region::active()->ordered()->get();

        $pickupLocations = Location::query()
            ->active()
            ->with('region')
            ->ordered()
            ->get()
            ->groupBy(fn (Location $location) => (string) $location->region?->name)
            ->map(fn ($group) => $group->map(fn (Location $location) => [
                'id' => $location->id,
                'name' => $location->name,
                'fee' => (float) ($location->pickup_fee ?? 0),
                'eta' => $location->pickup_eta,
            ])->values());

        $region = session('checkout_region')
            ?: $customer?->addresses()->where('is_default', true)->value('state');

        $rememberedLocationId = session('checkout_location_id');

        $quote = $this->pricing->quote($lines, $this->orders->sessionCoupon(), $region);

        $paymentGateways = PaymentGateway::with('configs')
            ->where('is_active', 1)
            ->where('code', '!=', PaymentMethodResolver::POD_CODE)
            ->get()
            ->filter(function (PaymentGateway $gateway) {
                if ($gateway->code === MobileMoney::GATEWAY_CODE) {
                    return $this->mobileMoney->isConfigured();
                }

                return PaymentManager::supports($gateway->code) && $gateway->isConfigured();
            })
            ->values();

        $paypal = $paymentGateways->firstWhere('code', 'paypal');
        $paypalClientId = $paypal
            ? $paypal->getConfigValueAny(['client_id', 'client_key'], null, $paypal->preferredEnvironment())
            : null;

        $subtotal = $quote['subtotal'];
        $discount = $quote['discount'];
        $tax = $quote['tax'];
        $shipping = $quote['shipping'];
        $total = $quote['total'];
        $freeShippingThreshold = $quote['free_shipping_threshold'];
        $coupon = $this->orders->sessionCoupon();

        return view('themes.xylo.checkout', compact(
            'cart', 'subtotal', 'discount', 'tax', 'shipping', 'total', 'coupon', 'quote',
            'paymentGateways', 'paypalClientId', 'regions', 'freeShippingThreshold', 'customer',
            'pickupLocations', 'rememberedLocationId'
        ));
    }

    public function process(Request $request)
    {
        $gatewayCode = $request->input('gateway');
        $currency = getWebConfig('default_currency', 'TZS');

        $gateway = $this->paymentMethods->activeGateway($gatewayCode);

        if (! $gateway) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose an available instant payment method.',
            ], 422);
        }

        $options = $this->captureCheckoutFulfillment($request);
        $amount = $this->checkoutQuoteTotal($options);

        if ($amount <= 0) {
            return response()->json(['success' => false, 'message' => 'Your cart is empty.'], 422);
        }

        try {
            $paymentService = PaymentManager::make($gateway->code);
            $order = $paymentService->createOrder($amount, $currency);

            return response()->json([
                'success' => true,
                'gateway' => $gateway->code,
                'order' => $order,
            ]);
        } catch (\Exception $e) {
            Log::error('Payment process failed: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Server-side order total (never trust a client-supplied amount).
     */
    protected function cartTotal(): float
    {
        return $this->checkoutQuoteTotal();
    }

    /**
     * Remember the shopper's fulfillment choice so gateway callbacks can price
     * the order the same way the checkout screen did, then return the options.
     *
     * @return array{fulfillment:string, pickup_location:?string, region:?string}
     */
    protected function captureCheckoutFulfillment(Request $request): array
    {
        $region = $request->input('region');
        $fulfillment = $request->input('fulfillment_type', 'delivery');
        $pickupName = null;

        if ($fulfillment === 'pickup') {
            $pickupName = Location::query()
                ->active()
                ->whereKey($request->input('pickup_location_id'))
                ->whereHas('region', fn ($query) => $query->where('name', $region))
                ->value('name');
        }

        session([
            'checkout_fulfillment' => $fulfillment,
            'checkout_pickup' => $pickupName,
            'checkout_region' => $region,
        ]);

        return [
            'fulfillment' => $fulfillment,
            'pickup_location' => $pickupName,
            'region' => $region,
        ];
    }

    /**
     * Price the current cart using the remembered fulfillment choice.
     */
    protected function checkoutQuoteTotal(?array $options = null): float
    {
        $options ??= [
            'fulfillment' => session('checkout_fulfillment', 'delivery'),
            'pickup_location' => session('checkout_pickup'),
            'region' => session('checkout_region'),
        ];

        return $this->pricing->quote(
            $this->orders->cartLines(),
            $this->orders->sessionCoupon(),
            $options['region'] ?? null,
            null,
            $options
        )['total'];
    }

    /**
     * PayPal success callback
     */
    /**
     * Post-placement confirmation that shows the buyer how to track the order
     * they have just placed. The order is resolved from the checkout flash id
     * scoped to the signed-in customer, falling back to their most recent order.
     */
    public function thankyou()
    {
        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return redirect()->route('customer.login');
        }

        $orderId = session('order_id');

        $order = Order::with('statusHistories')
            ->where('customer_id', $customer->id)
            ->when($orderId, fn ($query) => $query->where('id', $orderId))
            ->latest('id')
            ->first();

        return view('themes.xylo.thankyou', compact('order'));
    }

    public function paypalSuccess(Request $request)
    {
        $paypalOrderId = $request->query('token');
        $pending = session('checkout_pending');

        if (! $paypalOrderId || ! is_array($pending) || ($pending['method'] ?? null) !== 'paypal') {
            return redirect()->route('checkout.index')
                ->with('error', 'The payment session expired. Please try again.');
        }

        try {
            $paypal = PaymentManager::make('paypal');
            $result = $paypal->captureOrder($paypalOrderId);

            if (($result['status'] ?? null) !== 'COMPLETED') {
                return redirect()->route('checkout.index')
                    ->with('error', 'PayPal did not complete the payment. Please try again.');
            }

            $captured = (float) ($result['purchase_units'][0]['payments']['captures'][0]['amount']['value'] ?? 0);
            $expected = (float) ($pending['expected_total'] ?? 0);

            if ($expected <= 0 || abs($captured - $expected) > 0.01) {
                Log::warning('PayPal amount mismatch', [
                    'captured' => $captured,
                    'expected' => $expected,
                    'paypal_order' => $paypalOrderId,
                ]);

                return redirect()->route('checkout.index')
                    ->with('error', 'The captured amount does not match your order. Please contact support.');
            }

            $payment = $this->paymentMethods->resolve(PaymentMethodResolver::CATEGORY_INSTANT, 'paypal');

            return $this->placeOrder(
                $pending['data'],
                $payment,
                null,
                ['source' => 'paypal', 'paypal_order_id' => $paypalOrderId],
                false,
                $pending['customer_id'] ?? null,
                $expected,
                $result
            );
        } catch (\Throwable $e) {
            Log::error('PayPal success error: '.$e->getMessage());

            return redirect()->route('checkout.index')
                ->with('error', 'We could not confirm your PayPal payment: '.$e->getMessage());
        }
    }

    public function paypalCancel()
    {
        session()->forget('checkout_pending');

        return redirect()->route('checkout.index')
            ->with('error', 'Payment was cancelled. Your cart is still saved.');
    }

    public function stripeSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        $pending = session('checkout_pending');

        if (! $sessionId || ! is_array($pending) || ($pending['method'] ?? null) !== 'stripe') {
            return redirect()->route('checkout.index')
                ->with('error', 'The payment session expired. Please try again.');
        }

        try {
            $stripe = PaymentManager::make('stripe');
            $checkout = $stripe->retrieveCheckoutSession($sessionId);

            if (($checkout->payment_status ?? null) !== 'paid') {
                return redirect()->route('checkout.index')
                    ->with('error', 'Stripe did not complete the payment. Please try again.');
            }

            $expected = (float) ($pending['expected_total'] ?? 0);
            $currency = strtoupper((string) ($pending['currency'] ?? ''));
            $captured = (float) (($checkout->amount_total ?? 0) / 100);

            if (in_array($currency, ['BIF', 'CLP', 'DJF', 'GNF', 'JPY', 'KMF', 'KRW', 'MGA', 'PYG', 'RWF', 'UGX', 'VND', 'VUV', 'XAF', 'XOF', 'XPF'], true)) {
                $captured = (float) ($checkout->amount_total ?? 0);
            }

            if ($expected <= 0 || abs($captured - $expected) > 0.01) {
                Log::warning('Stripe amount mismatch', [
                    'captured' => $captured,
                    'expected' => $expected,
                    'stripe_session' => $sessionId,
                ]);

                return redirect()->route('checkout.index')
                    ->with('error', 'The captured amount does not match your order. Please contact support.');
            }

            $payment = $this->paymentMethods->resolve(PaymentMethodResolver::CATEGORY_INSTANT, 'stripe');

            return $this->placeOrder(
                $pending['data'],
                $payment,
                null,
                ['source' => 'stripe', 'stripe_session' => $sessionId],
                false,
                $pending['customer_id'] ?? null,
                $expected,
                (array) $checkout
            );
        } catch (\Throwable $e) {
            Log::error('Stripe success error: '.$e->getMessage());

            return redirect()->route('checkout.index')
                ->with('error', 'We could not confirm your Stripe payment: '.$e->getMessage());
        }
    }

    public function stripeCancel()
    {
        session()->forget('checkout_pending');

        return redirect()->route('checkout.index')
            ->with('error', 'Payment was cancelled. Your cart is still saved.');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255|required_if:fulfillment_type,delivery',
            'suite' => 'nullable|string|max:255',
            'region' => 'required|string|max:255',
            'city' => 'nullable|string|max:255|required_if:fulfillment_type,delivery',
            'zipcode' => 'nullable|string|max:50',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:50',
            'fulfillment_type' => 'nullable|in:delivery,pickup',
            'pickup_location_id' => 'nullable|integer',
            'payment_method_category' => 'nullable|in:instant_pay,pay_on_delivery',
            'gateway' => 'nullable|string|max:50',
            'mobile_money_provider' => 'nullable|string|max:50',
            'mobile_money_number' => 'nullable|string|max:20',
            'save_address' => 'nullable|boolean',
        ]);

        $customer = Auth::guard('customer')->user();

        if (! $customer) {
            return redirect()->route('customer.login')
                ->with('error', 'Please sign in to place an order.');
        }

        $regionModel = Region::where('name', $data['region'])->first();
        if (! $regionModel || ! $regionModel->is_active) {
            return back()->withErrors(['region' => 'Please choose an active delivery region.'])->withInput();
        }

        $fulfillment = $data['fulfillment_type'] ?? 'delivery';
        if ($fulfillment !== 'pickup') {
            $deliveryLocation = Location::query()
                ->active()
                ->where('region_id', $regionModel->id)
                ->where('name', $data['city'] ?? '')
                ->first();

            if ($deliveryLocation && ! $deliveryLocation->isDeliveryCovered()) {
                return back()->withErrors(['city' => 'This area is currently unavailable for delivery.'])->withInput();
            }
        }

        // Authoritative server-side resolution of the payment method.
        try {
            $payment = $this->paymentMethods->resolve(
                $data['payment_method_category'] ?? null,
                $data['gateway'] ?? null
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        if ($payment['category'] === PaymentMethodResolver::CATEGORY_POD
            && ! Region::where('name', $data['region'])->where('is_active', true)->exists()) {
            return back()->withErrors([
                'gateway' => 'Pay on Delivery is not available for the selected region.',
            ])->withInput();
        }

        if ($payment['category'] === PaymentMethodResolver::CATEGORY_INSTANT
            && PaymentManager::supports($payment['method'])) {
            return $this->redirectToHostedGateway($data, $payment, $customer);
        }

        try {
            return $this->placeOrder(
                $data,
                $payment,
                null,
                [],
                $request->boolean('save_address'),
                $customer->id
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }
    }

    /**
     * Send the shopper to PayPal/Stripe with a server-side total and remember
     * the checkout form so the callback can create the order after payment.
     */
    protected function redirectToHostedGateway(array $data, array $payment, Customer $customer)
    {
        if (! $payment['gateway'] || ! $payment['gateway']->isConfigured()) {
            return back()->withErrors([
                'gateway' => 'This payment option is not configured yet. Please choose another method.',
            ])->withInput();
        }

        try {
            $context = $this->checkoutContext($data, $customer->id);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        Session::put('checkout_pending', [
            'data' => $data,
            'method' => $payment['method'],
            'expected_total' => (float) $context['quote']['total'],
            'currency' => (string) $context['quote']['currency'],
            'customer_id' => $customer->id,
            'created_at' => now()->timestamp,
        ]);

        try {
            $service = PaymentManager::make($payment['method']);

            if ($payment['method'] === 'paypal') {
                $order = $service->createOrder((float) $context['quote']['total'], (string) $context['quote']['currency']);
                $url = $service->approvalUrl($order);

                if (! $url) {
                    throw new \RuntimeException('PayPal did not return an approval link.');
                }

                Session::put('checkout_pending.paypal_order', $order['id'] ?? null);

                return redirect()->away($url);
            }

            $stripe = $service->createCheckoutSession(
                (float) $context['quote']['total'],
                (string) $context['quote']['currency'],
                route('stripe.checkout.success').'?session_id={CHECKOUT_SESSION_ID}',
                route('stripe.checkout.cancel')
            );

            Session::put('checkout_pending.stripe_session', $stripe->id);

            return redirect()->away($stripe->url);
        } catch (\Throwable $e) {
            Session::forget('checkout_pending');
            Log::error('Payment redirect failed: '.$e->getMessage());

            return back()->withErrors([
                'gateway' => 'The payment provider could not be reached: '.$e->getMessage(),
            ])->withInput();
        }
    }

    /**
     * Validate the cart and fulfilment once, then create the order(s).
     */
    protected function checkoutContext(array $data, ?int $customerId = null): array
    {
        $fulfillment = $data['fulfillment_type'] ?? 'delivery';
        $pickupLocation = null;

        if ($fulfillment === 'pickup') {
            $pickupLocation = Location::query()
                ->active()
                ->whereKey($data['pickup_location_id'] ?? null)
                ->whereHas('region', fn ($query) => $query->where('name', $data['region']))
                ->first();

            if (! $pickupLocation) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'pickup_location_id' => 'Please choose an active pickup station in your region.',
                ]);
            }
        }

        $lines = $this->orders->cartLines();

        if (empty($lines)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cart' => 'Your cart is empty.',
            ]);
        }

        $coupon = $this->orders->sessionCoupon();
        $quote = $this->pricing->quote($lines, $coupon, $data['region'], null, [
            'fulfillment' => $fulfillment,
            'pickup_location' => $pickupLocation?->name,
            'location' => $data['city'] ?? session('checkout_location'),
        ]);

        $minOrderValue = BusinessRule::number('minimum_order_value', 0);
        if ($minOrderValue > 0 && $quote['subtotal'] < $minOrderValue) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cart' => 'Orders must be at least '.number_format($minOrderValue).' '.getWebConfig('default_currency', 'TZS').'.',
            ]);
        }

        $customer = $customerId ? Customer::find($customerId) : Auth::guard('customer')->user();

        if (! $customer) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer' => 'Please sign in to place an order.',
            ]);
        }

        return compact('fulfillment', 'pickupLocation', 'lines', 'coupon', 'quote', 'customer');
    }

    /**
     * Create the order(s), settle the payment and send the buyer to the
     * confirmation page.
     */
    protected function placeOrder(
        array $data,
        array $payment,
        ?array $mobile = null,
        array $extraMeta = [],
        bool $saveAddress = false,
        ?int $customerId = null,
        ?float $expectedTotal = null,
        array $gatewayResponse = []
    ) {
        $context = $this->checkoutContext($data, $customerId);

        if ($expectedTotal !== null && abs((float) $context['quote']['total'] - $expectedTotal) > 0.01) {
            return redirect()->route('checkout.index')
                ->with('error', 'Your cart changed while paying. Please review and try again.');
        }

        $fulfillment = $context['fulfillment'];
        $pickupLocation = $context['pickupLocation'];
        $lines = $context['lines'];
        $coupon = $context['coupon'];
        $quote = $context['quote'];
        $customer = $context['customer'];

        // Mobile money is validated before anything is written. The shopper must
        // never end up with a charge and no order, nor with an order that was
        // never charged.
        if ($payment['method'] === MobileMoney::GATEWAY_CODE) {
            try {
                $mobile = $this->mobileMoney->validate(
                    $data['mobile_money_provider'] ?? null,
                    $data['mobile_money_number'] ?? null
                );
            } catch (\Illuminate\Validation\ValidationException $e) {
                return back()->withErrors($e->errors())->withInput();
            }

            if (! $this->mobileMoney->isConfigured()) {
                return back()->withErrors([
                    'mobile_money_provider' => 'Mobile money is not available right now. Please choose another payment method.',
                ])->withInput();
            }
        }

        $paymentMeta = array_merge([
            'source' => 'checkout',
            'category' => $payment['category'],
        ], $extraMeta);

        if ($mobile !== null) {
            $paymentMeta['mobile_money'] = [
                'provider' => $mobile['provider'],
                'msisdn' => $mobile['msisdn'],
            ];
        }

        $shippingRegion = Region::where('name', $data['region'])->first();
        $shippingLocation = null;
        if ($fulfillment === 'pickup' && $pickupLocation) {
            $shippingLocation = $pickupLocation;
        } elseif (! empty($data['city'])) {
            $shippingLocation = Location::query()
                ->where('region_id', $shippingRegion?->id)
                ->where('name', $data['city'])
                ->first();
        }

        $shippingData = $fulfillment === 'pickup' && $pickupLocation
            ? [
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'phone' => $data['phone'],
                'address' => 'Pickup station: '.$pickupLocation->name,
                'city' => $pickupLocation->name,
                'state' => $data['region'],
                'region_id' => $shippingRegion?->id,
                'location_id' => $shippingLocation?->id,
                'postal_code' => $pickupLocation->postal_code ?? '',
                'country' => 'Tanzania',
            ]
            : [
                'name' => trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')),
                'phone' => $data['phone'],
                'address' => trim(($data['address'] ?? '').' '.($data['suite'] ?? '')),
                'city' => $data['city'],
                'state' => $data['region'],
                'region_id' => $shippingRegion?->id,
                'location_id' => $shippingLocation?->id,
                'postal_code' => $data['zipcode'] ?? '',
                'country' => 'Tanzania',
            ];

        $hostedPaid = PaymentManager::supports($payment['method']);

        try {
            $orders = $this->orders->createOrders($lines, $shippingData, $coupon, [
                'customer_id' => $customer->id,
                'guest_email' => null,
                'is_guest' => false,
                'payment_method' => $payment['method'],
                'status' => 'pending',
                'payment_status' => $hostedPaid ? 'paid' : 'pending',
                'region' => $data['region'],
                'fulfillment_type' => $fulfillment,
                'pickup_location_id' => $pickupLocation?->id,
                'pickup_location_name' => $pickupLocation?->name,
                'payment_meta' => $paymentMeta,
                // The buyer is only told the order is placed once the charge
                // has been handed to the aggregator.
                'notify' => $mobile === null,
            ]);
        } catch (InsufficientStockException $e) {
            return redirect()->route('cart.view')
                ->with('error', 'Sorry, an item in your cart just sold out. Please review your cart.');
        }

        if ($hostedPaid) {
            $transactionId = $extraMeta['paypal_order_id'] ?? $extraMeta['stripe_session'] ?? null;

            foreach ($orders as $order) {
                $this->payments->markCompleted($order, $gatewayResponse, $transactionId);
            }
        }

        if ($mobile !== null) {
            $charge = $this->chargeMobileMoney($orders, $mobile);

            if (! $charge['ok']) {
                $this->cancelUnpaidOrders($orders, 'The mobile money charge could not be started.');

                return back()->withErrors([
                    'mobile_money_provider' => $charge['message'],
                ])->withInput();
            }

            $this->recordMobileMoneyCharge($orders, $mobile, $charge['charge']);
            $this->orders->notifyOrders($orders, ['customer_id' => $customer->id]);
        }

        if ($customer && $fulfillment === 'delivery' && $saveAddress) {
            $this->saveAddress($customer, $shippingData, $data['region']);
        }

        Session::forget([
            'cart', 'cart_count', 'cart_coupon',
            'checkout_fulfillment', 'checkout_pickup', 'checkout_region',
            'checkout_location', 'checkout_location_id', 'checkout_pending',
        ]);

        return redirect()->route('thankyou')
            ->with('success', 'Order placed successfully!')
            ->with('order_id', $orders->first()?->id);
    }

    /**
     * Hand the cart total to the aggregator once the order(s) exist, so every
     * charge carries an order reference the callback can be reconciled against.
     *
     * @param  Collection<int,Order>  $orders
     * @return array{ok:bool, message?:string, charge?:array}
     */
    protected function chargeMobileMoney(Collection $orders, array $mobile): array
    {
        $reference = 'ORDER-'.$orders->first()?->id;
        $amount = (float) $orders->sum('total_amount');

        try {
            $charge = $this->mobileMoney->requestPayment(
                $mobile['provider'],
                $mobile['msisdn'],
                $amount,
                ['reference' => $reference]
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            $message = $e->errors()['mobile_money_provider'][0]
                ?? 'The mobile money charge could not be started.';

            Log::warning('Mobile money charge could not be started', [
                'reference' => $reference,
                'amount' => $amount,
                'message' => $message,
            ]);

            return ['ok' => false, 'message' => $message];
        }

        $charge['merchant_reference'] = $reference;

        return ['ok' => true, 'charge' => $charge];
    }

    /**
     * Store the aggregator hand-off on every payment row of the checkout so
     * the callback (and any admin reconciliation) can see which network was
     * charged, for which number, under which reference.
     *
     * @param  Collection<int,Order>  $orders
     */
    protected function recordMobileMoneyCharge(Collection $orders, array $mobile, array $charge): void
    {
        $orderIds = $orders->pluck('id')->all();

        foreach ($orders as $order) {
            $payment = Payment::where('order_id', $order->id)->latest('id')->first();

            if (! $payment) {
                continue;
            }

            $meta = $payment->meta ?? [];

            $meta['mobile_money'] = array_merge($meta['mobile_money'] ?? [], [
                'provider' => $mobile['provider'],
                'msisdn' => $mobile['msisdn'],
                'reference' => $charge['reference'] ?? null,
                'merchant_reference' => $charge['merchant_reference'] ?? null,
                'status' => $charge['status'] ?? 'pending',
                'message' => $charge['message'] ?? null,
                'order_ids' => $orderIds,
            ]);

            $payment->update(['meta' => $meta]);
        }
    }

    /**
     * Roll an unpaid checkout back: the order is canceled (which restocks the
     * items and reverses the earnings) and the pending payment is marked failed
     * so it never looks like a live charge.
     *
     * @param  Collection<int,Order>  $orders
     */
    protected function cancelUnpaidOrders(Collection $orders, string $reason): void
    {
        $workflow = app(OrderWorkflowService::class);

        foreach ($orders as $order) {
            try {
                $workflow->transition($order, 'canceled', $reason, 'system');
                $this->payments->markFailed($order, ['reason' => $reason]);
            } catch (\Throwable $e) {
                Log::error('Could not cancel order #'.$order->id.' after a failed mobile money charge', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    protected function saveAddress(Customer $customer, array $shipping, string $region): void
    {
        if ($customer->addresses()->where('address', $shipping['address'])->where('city', $shipping['city'])->exists()) {
            return;
        }

        $isFirst = $customer->addresses()->count() === 0;

        $customer->addresses()->create([
            'label' => $isFirst ? 'Home' : 'Address',
            'name' => $shipping['name'],
            'phone' => $shipping['phone'],
            'address' => $shipping['address'],
            'city' => $shipping['city'],
            'state' => $region,
            'postal_code' => $shipping['postal_code'],
            'country' => $shipping['country'],
            'is_default' => $isFirst,
        ]);
    }
}
