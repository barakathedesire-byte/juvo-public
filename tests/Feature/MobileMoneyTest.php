<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Services\Payments\MobileMoney;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MobileMoneyTest extends TestCase
{
    use DatabaseTransactions;

    protected function variant(): ProductVariant
    {
        $vendor = Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
        $variant = Product::where('vendor_id', $vendor->id)->firstOrFail()
            ->variants()->where('is_primary', 1)->firstOrFail();
        $variant->update(['stock' => 10, 'price' => 10000, 'discount_price' => null]);

        return $variant;
    }

    protected function customer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Mobile Buyer',
            'email' => 'mobile-buyer-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    protected function cartFor(ProductVariant $variant): array
    {
        return [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => 1,
                'quantity' => 1,
            ],
        ];
    }

    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mobile',
            'last_name' => 'Buyer',
            'address' => '1 Test St',
            'region' => 'Mwanza',
            'city' => 'Mwanza',
            'email' => 'buyer@example.com',
            'phone' => '0700000000',
            'payment_method_category' => 'instant_pay',
            'gateway' => 'mobilemoney',
        ], $overrides);
    }

    public function test_provider_catalogue_is_exposed(): void
    {
        $providers = app(MobileMoney::class)->providers();

        $this->assertArrayHasKey('mpesa', $providers);
        $this->assertArrayHasKey('mixx', $providers);
        $this->assertArrayHasKey('airtel', $providers);
        $this->assertArrayHasKey('halopesa', $providers);
        $this->assertArrayHasKey('tpesa', $providers);
    }

    public function test_msisdn_is_normalised_across_formats(): void
    {
        $service = app(MobileMoney::class);

        $this->assertSame('0754123456', $service->normaliseMsisdn('0754123456'));
        $this->assertSame('0754123456', $service->normaliseMsisdn('+255754123456'));
        $this->assertSame('0754123456', $service->normaliseMsisdn('255754123456'));
        $this->assertNull($service->normaliseMsisdn('12345'));
    }

    public function test_network_mismatch_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(MobileMoney::class)->validate('mpesa', '0712000000');
    }

    public function test_checkout_requires_a_network_when_mobile_money_selected(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'mobile_money_provider' => '',
                'mobile_money_number' => '',
            ]))
            ->assertSessionHasErrors('mobile_money_provider');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_checkout_blocks_when_aggregator_is_not_configured(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->assertFalse(app(MobileMoney::class)->isConfigured());

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'mobile_money_provider' => 'mpesa',
                'mobile_money_number' => '0754123456',
            ]))
            ->assertSessionHasErrors('mobile_money_provider');

        $this->assertSame(0, Order::where('customer_id', $customer->id)->count());
    }

    public function test_checkout_places_order_and_records_charge_when_configured(): void
    {
        config()->set('mobile_money.aggregator.base_url', 'https://aggregator.test');
        config()->set('mobile_money.aggregator.api_key', 'test-key');
        config()->set('mobile_money.aggregator.vendor_id', 'juvo');

        Http::fake([
            'aggregator.test/*' => Http::response([
                'status' => 'pending',
                'reference' => 'AGG-999',
                'message' => 'Enter your PIN',
            ], 200),
        ]);

        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'email' => $customer->email,
                'mobile_money_provider' => 'mpesa',
                'mobile_money_number' => '+255754123456',
            ]))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $order->payment_status);

        $payment = \App\Models\Payment::where('order_id', $order->id)->latest('id')->firstOrFail();
        $meta = is_array($payment->meta) ? $payment->meta : json_decode((string) $payment->meta, true);
        $this->assertSame('pending', $meta['mobile_money']['status']);
        $this->assertSame('0754123456', $meta['mobile_money']['msisdn']);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/collections')
            && $request['msisdn'] === '0754123456'
            && $request['amount'] > 0);
    }

    public function test_storefront_url_escapes_the_portal_prefix(): void
    {
        config()->set('app.url', 'https://example.com');

        $this->assertSame('https://example.com/customer/login', storefront_url('/customer/login'));
    }

    public function test_the_charge_carries_the_order_reference(): void
    {
        $this->configureAggregator();

        Http::fake([
            'aggregator.test/*' => Http::response(['status' => 'pending', 'reference' => 'AGG-999'], 200),
        ]);

        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'email' => $customer->email,
                'mobile_money_provider' => 'mpesa',
                'mobile_money_number' => '0754123456',
            ]))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        Http::assertSent(fn ($request) => $request['reference'] === 'ORDER-'.$order->id);
    }

    public function test_a_failed_charge_cancels_the_order_and_restocks_the_items(): void
    {
        $this->configureAggregator();

        Http::fake([
            'aggregator.test/*' => Http::response(['status' => 'failed'], 500),
        ]);

        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'email' => $customer->email,
                'mobile_money_provider' => 'mpesa',
                'mobile_money_number' => '0754123456',
            ]))
            ->assertSessionHasErrors('mobile_money_provider');

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame('canceled', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame(10, (int) $variant->fresh()->stock);
    }

    public function test_mobile_money_is_only_offered_when_the_aggregator_is_configured(): void
    {
        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->get(route('checkout.index'))
            ->assertDontSee('value="mobilemoney"', false);

        $this->configureAggregator();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->get(route('checkout.index'))
            ->assertSee('value="mobilemoney"', false);
    }

    public function test_the_callback_settles_a_paid_collection(): void
    {
        $this->configureAggregator();
        config()->set('mobile_money.aggregator.callback_secret', 'callback-secret');

        Http::fake([
            'aggregator.test/*' => Http::response(['status' => 'pending', 'reference' => 'AGG-999'], 200),
        ]);

        $variant = $this->variant();
        $customer = $this->customer();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $this->cartFor($variant)])
            ->post(route('checkout.store'), $this->payload([
                'email' => $customer->email,
                'mobile_money_provider' => 'mpesa',
                'mobile_money_number' => '0754123456',
            ]))
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertSame('pending', $order->payment_status);

        $body = json_encode([
            'status' => 'success',
            'merchant_reference' => 'ORDER-'.$order->id,
            'transaction_id' => 'TX-1',
        ]);

        $this->postCallback($body, hash_hmac('sha256', $body, 'callback-secret'))->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('completed', Payment::where('order_id', $order->id)->latest('id')->first()->status);
    }

    public function test_the_callback_rejects_an_unsigned_request(): void
    {
        $body = json_encode(['status' => 'success', 'merchant_reference' => 'ORDER-1']);

        $this->postCallback($body, 'not-the-signature')->assertForbidden();
    }

    public function test_an_ad_campaign_keeps_the_mobile_money_details(): void
    {
        $vendor = $this->vendor();
        $product = Product::where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.ads.store'), [
            'product_id' => $product->id,
            'title' => 'Mobile money campaign',
            'total_budget' => 50000,
            'days' => 10,
            'payment_method' => 'mobilemoney',
            'mobile_money_provider' => 'mpesa',
            'mobile_money_number' => '0754123456',
        ])->assertRedirect(route('vendor.ads.index'));

        $campaign = AdCampaign::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();

        $this->assertSame('mobilemoney', $campaign->payment_method);
        $this->assertSame('mpesa', $campaign->payment_meta['provider']);
        $this->assertSame('0754123456', $campaign->payment_meta['msisdn']);
        $this->assertNull($campaign->paid_at);
        $this->assertSame(0.0, (float) $campaign->amount_paid);
    }

    public function test_an_ad_campaign_is_charged_when_the_aggregator_is_configured(): void
    {
        $this->configureAggregator();

        Http::fake([
            'aggregator.test/*' => Http::response(['status' => 'pending', 'reference' => 'AGG-777'], 200),
        ]);

        $vendor = $this->vendor();
        $product = Product::where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.ads.store'), [
            'product_id' => $product->id,
            'title' => 'Charged campaign',
            'total_budget' => 50000,
            'days' => 10,
            'payment_method' => 'mobilemoney',
            'mobile_money_provider' => 'mpesa',
            'mobile_money_number' => '0754123456',
        ])->assertRedirect(route('vendor.ads.index'));

        $campaign = AdCampaign::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();

        $this->assertSame('AD-'.$campaign->id, $campaign->payment_meta['merchant_reference']);
        $this->assertSame('AGG-777', $campaign->payment_meta['reference']);
        $this->assertSame('awaiting_payment', $campaign->status);

        Http::assertSent(fn ($request) => $request['reference'] === 'AD-'.$campaign->id
            && (float) $request['amount'] === 50000.0);
    }

    protected function configureAggregator(): void
    {
        config()->set('mobile_money.aggregator.base_url', 'https://aggregator.test');
        config()->set('mobile_money.aggregator.api_key', 'test-key');
        config()->set('mobile_money.aggregator.vendor_id', 'juvo');
    }

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function postCallback(string $body, ?string $signature)
    {
        return $this->call('POST', route('payments.mobile-money.callback'), [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_SIGNATURE' => $signature,
        ], $body);
    }
}
