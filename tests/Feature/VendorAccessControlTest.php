<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorAccessControlTest extends TestCase
{
    use DatabaseTransactions;

    protected function activeVendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function makeVendor(string $email): Vendor
    {
        return Vendor::create([
            'name' => 'Rival Vendor',
            'shop_name' => 'Rival Shop',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => 1,
        ]);
    }

    public function test_suspended_vendor_products_are_hidden_from_public_api(): void
    {
        $vendor = $this->activeVendor();

        $this->assertGreaterThan(0, Product::visible()->where('vendor_id', $vendor->id)->count());

        $vendor->update(['status' => 'inactive']);

        $this->assertSame(0, Product::visible()->where('vendor_id', $vendor->id)->count());

        $this->getJson('/api/products')
            ->assertOk()
            ->assertJsonPath('status', true);
    }

    public function test_add_to_cart_rejects_a_suspended_vendors_product(): void
    {
        $vendor = $this->activeVendor();
        $product = Product::where('vendor_id', $vendor->id)->firstOrFail();

        $vendor->update(['status' => 'banned']);

        $this->postJson('/cart/add', [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_suspended_vendor_is_logged_out_of_the_panel(): void
    {
        $vendor = $this->activeVendor();
        $vendor->update(['status' => 'inactive']);

        $this->actingAs($vendor, 'vendor')
            ->get('/vendor/dashboard')
            ->assertRedirect(route('vendor.login'));
    }

    public function test_vendor_cannot_view_another_vendors_product(): void
    {
        $owner = $this->activeVendor();
        $product = Product::where('vendor_id', $owner->id)->firstOrFail();

        $rival = $this->makeVendor('rival-product@example.com');

        $this->actingAs($rival, 'vendor')
            ->get('/vendor/products/'.$product->id.'/edit')
            ->assertNotFound();
    }

    public function test_vendor_cannot_delete_another_vendors_order(): void
    {
        $owner = $this->activeVendor();
        $product = Product::where('vendor_id', $owner->id)->firstOrFail();

        $order = Order::create([
            'vendor_id' => $owner->id,
            'total_amount' => 100,
            'status' => 'pending',
        ]);

        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 100,
        ]);

        $rival = $this->makeVendor('rival-order@example.com');

        $this->actingAs($rival, 'vendor')
            ->delete('/vendor/orders/'.$order->id)
            ->assertNotFound();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
