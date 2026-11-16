<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorVerificationDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PublicEndpointHardeningTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function customer(string $label): Customer
    {
        return Customer::create([
            'name' => 'Buyer '.$label,
            'email' => 'buyer-'.$label.'-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 'active',
        ]);
    }

    protected function orderFor(Customer $customer): Order
    {
        return Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 20000,
            'total_amount' => 20000,
            'delivery_fee' => 0,
            'status' => 'pending',
            'currency' => 'TZS',
            'payment_status' => 'pending',
        ]);
    }

    protected function pendingPesapalPayment(Order $order): Payment
    {
        $gateway = PaymentGateway::firstOrCreate(
            ['code' => 'pesapal'],
            ['name' => 'Pesapal', 'is_active' => 1]
        );

        return Payment::create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'gateway_id' => $gateway->id,
            'merchant_reference' => 'ORD-'.$order->id.'-TEST',
            'amount' => $order->total_amount,
            'currency' => $order->currency,
            'payment_method' => 'pesapal',
            'status' => 'pending',
        ]);
    }

    public function test_pesapal_callbacks_reject_another_customers_order(): void
    {
        $owner = $this->customer('owner');
        $intruder = $this->customer('intruder');
        $order = $this->orderFor($owner);
        $payment = $this->pendingPesapalPayment($order);

        $this->actingAs($intruder, 'customer')
            ->get(route('pesapal.return', $order))
            ->assertForbidden();

        $this->actingAs($intruder, 'customer')
            ->get(route('pesapal.cancel', $order))
            ->assertForbidden();

        $this->assertSame('pending', $payment->fresh()->status);

        $this->actingAs($owner, 'customer')
            ->get(route('pesapal.cancel', $order))
            ->assertRedirect(route('customer.orders.show', $order));

        $this->assertSame('failed', $payment->fresh()->status);
    }

    public function test_guests_cannot_submit_product_reviews(): void
    {
        $product = Product::visible()->firstOrFail();

        $this->post(route('review.store'), [
            'product_id' => $product->id,
            'rating' => 5,
            'review' => 'Injected review',
        ])->assertRedirect(route('customer.login'));

        $this->assertDatabaseMissing('product_reviews', [
            'product_id' => $product->id,
            'review' => 'Injected review',
        ]);
    }

    public function test_guests_cannot_create_stripe_payment_intents(): void
    {
        $this->get(route('stripe.checkout.process'))
            ->assertRedirect(route('customer.login'));
    }

    public function test_rider_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/rider/login', [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ]);
        }

        $this->post('/rider/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_pesapal_webhook_rejects_a_mismatched_notification_id(): void
    {
        config(['pesapal.ipn.notification_id' => 'expected-notification-id']);

        $this->post(route('pesapal.webhook').'?OrderTrackingId=tracking&OrderMerchantReference=reference&OrderNotificationId=wrong')
            ->assertForbidden();
    }

    public function test_browser_hardening_headers_are_sent(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_tracking_embeds_are_allowed_to_be_framed(): void
    {
        $this->get('/embed/track/not-a-real-token')
            ->assertNotFound()
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_vendor_documents_are_downloaded_through_an_authorized_route(): void
    {
        $vendor = Vendor::query()->firstOrFail();
        $path = 'vendor_documents/'.uniqid().'.txt';
        Storage::disk('local')->put($path, 'private vendor document');

        $document = VendorVerificationDocument::create([
            'vendor_id' => $vendor->id,
            'type' => 'business_document',
            'title' => 'Business document',
            'file_path' => $path,
            'disk' => 'local',
            'status' => 'pending',
        ]);

        $this->get(route('admin.vendors.documents.download', $document->id))
            ->assertRedirect(route('login'));

        $this->actingAs($this->admin())
            ->get(route('admin.vendors.documents.download', $document->id))
            ->assertOk()
            ->assertDownload(basename($path));

        Storage::disk('local')->delete($path);
    }
}
