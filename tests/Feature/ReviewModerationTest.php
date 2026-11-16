<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ReviewModerationTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function makeProduct(): Product
    {
        return Product::create([
            'shop_id' => Shop::query()->value('id'),
            'vendor_id' => Vendor::query()->value('id'),
            'slug' => 'rev-'.uniqid(),
            'category_id' => Category::firstOrFail()->id,
            'product_type' => 'variable',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);
    }

    protected function makeCustomer(): Customer
    {
        return Customer::create([
            'name' => 'Reviewer',
            'email' => 'reviewer-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
        ]);
    }

    public function test_review_on_a_delivered_order_is_marked_verified(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $order = Order::create([
            'vendor_id' => Vendor::query()->value('id'),
            'customer_id' => $customer->id,
            'subtotal' => 10000,
            'total_amount' => 10000,
            'status' => 'delivered',
            'currency' => 'TZS',
        ]);
        OrderDetail::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10000]);

        $this->actingAs($customer, 'customer')->post(route('review.store'), [
            'product_id' => $product->id,
            'rating' => 5,
            'review' => 'Excellent!',
        ])->assertRedirect();

        $review = ProductReview::where('product_id', $product->id)->firstOrFail();
        $this->assertTrue($review->is_verified_purchase);
    }

    public function test_review_without_a_purchase_is_not_verified(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $this->actingAs($customer, 'customer')->post(route('review.store'), [
            'product_id' => $product->id,
            'rating' => 4,
            'review' => 'Nice.',
        ])->assertRedirect();

        $review = ProductReview::where('product_id', $product->id)->firstOrFail();
        $this->assertFalse($review->is_verified_purchase);
    }

    public function test_admin_can_hide_and_approve_a_review(): void
    {
        $admin = $this->admin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct();

        $review = ProductReview::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
            'rating' => 3,
            'review' => 'Okay.',
            'is_approved' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.reviews.toggle', $review->id))
            ->assertOk()
            ->assertJsonPath('is_approved', false);

        $this->assertFalse($review->fresh()->is_approved);
        $this->assertFalse($product->reviews()->whereKey($review->id)->exists());

        $this->actingAs($admin)
            ->post(route('admin.reviews.toggle', $review->id))
            ->assertOk()
            ->assertJsonPath('is_approved', true);

        $this->assertTrue($review->fresh()->is_approved);
        $this->assertTrue($product->reviews()->whereKey($review->id)->exists());
    }
}
