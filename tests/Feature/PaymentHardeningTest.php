<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentGatewayConfig;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ShippingAddress;
use App\Models\Vendor;
use App\Services\Store\PaymentService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PaymentHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function activeVendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function variantFor(Vendor $vendor): ProductVariant
    {
        return Product::where('vendor_id', $vendor->id)->firstOrFail()
            ->variants()->where('is_primary', 1)->firstOrFail();
    }

    protected function verifiedCustomer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Payment Buyer',
            'email' => 'pay-buyer-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);
        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    public function test_encrypted_gateway_config_is_decrypted_on_read(): void
    {
        $gateway = PaymentGateway::create([
            'name' => 'Test Gateway',
            'code' => 'testgw-'.uniqid(),
            'is_active' => 0,
        ]);

        $config = PaymentGatewayConfig::create([
            'gateway_id' => $gateway->id,
            'key_name' => 'secret',
            'key_value' => 'super-secret-value',
            'is_encrypted' => true,
            'environment' => 'sandbox',
        ]);

        $this->assertSame('super-secret-value', $config->fresh()->key_value);

        $raw = DB::table('payment_gateway_configs')->where('id', $config->id)->value('key_value');
        $this->assertNotSame('super-secret-value', $raw);
        $this->assertSame('super-secret-value', Crypt::decryptString($raw));
    }

    public function test_checkout_records_a_pending_payment_and_stores_region(): void
    {
        $vendor = $this->activeVendor();
        $variant = $this->variantFor($vendor);
        $variant->update(['stock' => 5, 'price' => 10000, 'discount_price' => null]);

        $customer = $this->verifiedCustomer();
        $cart = [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => 1,
                'quantity' => 2,
            ],
        ];

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $cart])
            ->post(route('checkout.store'), [
                'first_name' => 'Pay',
                'last_name' => 'Buyer',
                'address' => '1 Test St',
                'region' => 'Mwanza',
                'city' => 'Mwanza',
                'email' => $customer->email,
                'phone' => '0700000000',
                'gateway' => 'payondelivery',
            ])
            ->assertRedirect(route('thankyou'));

        $order = Order::where('customer_id', $customer->id)->latest('id')->firstOrFail();

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'payment_method' => 'payondelivery',
            'status' => 'pending',
        ]);

        $payment = Payment::where('order_id', $order->id)->firstOrFail();
        $this->assertEquals(20000, (float) $payment->amount);

        $address = ShippingAddress::where('order_id', $order->id)->firstOrFail();
        $this->assertSame('Mwanza', $address->state);
    }

    public function test_mark_completed_settles_payment_and_order(): void
    {
        $gateway = PaymentGateway::firstOrCreate(
            ['code' => 'mobilemoney'],
            ['name' => 'Mobile', 'is_active' => 1]
        );

        $order = Order::create([
            'vendor_id' => $this->activeVendor()->id,
            'guest_email' => 'settle@example.com',
            'total_amount' => 20000,
            'subtotal' => 20000,
            'delivery_fee' => 0,
            'payment_status' => 'pending',
            'currency' => 'TZS',
            'status' => 'pending',
        ]);

        $service = app(PaymentService::class);
        $payment = $service->recordPending($order, 'mobilemoney');

        $this->assertSame($gateway->id, $payment->gateway_id);
        $this->assertSame('pending', $payment->status);

        $service->markCompleted($order, ['ok' => true], 'TX-999');

        $payment->refresh();
        $this->assertSame('completed', $payment->status);
        $this->assertSame('TX-999', $payment->transaction_id);
        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}
