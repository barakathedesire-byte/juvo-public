<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorSupportTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(array $attrs = []): Vendor
    {
        return Vendor::create([
            'name' => $attrs['name'] ?? 'Helper',
            'shop_name' => $attrs['shop_name'] ?? 'Helper Shop',
            'email' => $attrs['email'] ?? uniqid('sup').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    protected function productFor(Vendor $vendor): array
    {
        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => $vendor->shop_name ?? 'Shop',
            'slug' => 'shop-'.uniqid(),
            'status' => 'active',
        ]);
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'category_id' => Category::first()->id,
            'slug' => 'p-'.uniqid(),
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);
        $product->translation()->create(['language_code' => 'en', 'name' => 'Support Product', 'description' => 'd']);

        return [$product, $shop];
    }

    protected function customer(): Customer
    {
        return Customer::create([
            'name' => 'Buyer',
            'email' => uniqid('buyer').'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    protected function ticket(Vendor $vendor, ?Product $product = null): SupportTicket
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TKT-'.strtoupper(uniqid()),
            'vendor_id' => $vendor->id,
            'product_id' => $product?->id,
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'subject' => 'Question',
            'category' => 'product_inquiry',
            'priority' => 'normal',
            'status' => 'open',
        ]);

        SupportTicketMessage::create([
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'guest',
            'body' => 'Is this in stock?',
        ]);

        return $ticket;
    }

    public function test_vendor_has_no_buyer_ticket_channel(): void
    {
        $vendor = $this->vendor();
        $ticket = $this->ticket($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post('/vendor/support/tickets/'.$ticket->id.'/reply', ['body' => 'Hi'])
            ->assertNotFound();

        $this->actingAs($vendor, 'vendor')
            ->post('/vendor/support/tickets/'.$ticket->id.'/close')
            ->assertNotFound();

        $this->assertDatabaseMissing('support_ticket_messages', [
            'support_ticket_id' => $ticket->id,
            'sender_type' => 'vendor',
        ]);
    }

    public function test_storefront_product_inquiry_route_is_removed(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->productFor($vendor);

        $this->post('/product/'.$product->slug.'/inquiry', [
            'name' => 'Guest Buyer',
            'email' => 'guestbuyer@example.com',
            'message' => 'Do you deliver to Arusha?',
        ])->assertNotFound();

        $this->assertSame(0, SupportTicket::where('product_id', $product->id)->count());
    }

    public function test_vendor_can_reply_to_a_review(): void
    {
        $vendor = $this->vendor();
        [$product] = $this->productFor($vendor);
        $customer = $this->customer();

        $review = ProductReview::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 5,
            'review' => 'Great',
            'is_approved' => 1,
        ]);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.support.reviews.reply', $review->id), ['vendor_reply' => 'Thank you!'])
            ->assertRedirect();

        $this->assertSame('Thank you!', $review->fresh()->vendor_reply);
        $this->assertNotNull($review->fresh()->vendor_replied_at);
    }

    public function test_vendor_cannot_reply_to_review_of_another_vendors_product(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        [$productB] = $this->productFor($vendorB);
        $customer = $this->customer();

        $review = ProductReview::create([
            'customer_id' => $customer->id,
            'product_id' => $productB->id,
            'rating' => 5,
            'review' => 'Great',
            'is_approved' => 1,
        ]);

        $this->actingAs($vendorA, 'vendor')
            ->post(route('vendor.support.reviews.reply', $review->id), ['vendor_reply' => 'Hi'])
            ->assertForbidden();
    }
}
