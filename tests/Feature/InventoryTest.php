<?php

namespace Tests\Feature;

use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Services\Store\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use DatabaseTransactions;

    protected function activeVendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function variantFor(Vendor $vendor): ProductVariant
    {
        $product = Product::where('vendor_id', $vendor->id)->firstOrFail();

        return $product->variants()->where('is_primary', 1)->firstOrFail();
    }

    protected function verifiedCustomer(): Customer
    {
        $customer = Customer::create([
            'name' => 'Inventory Buyer',
            'email' => 'inv-buyer-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);

        $customer->forceFill(['email_verified_at' => now()])->save();

        return $customer;
    }

    public function test_decrement_reduces_stock_and_records_a_sale_movement(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $variant->update(['stock' => 10]);

        $movement = app(InventoryService::class)->decrement($variant, 3, ['reason' => 'unit test']);

        $this->assertSame(7, $variant->fresh()->stock);
        $this->assertSame('sale', $movement->type);
        $this->assertSame(-3, $movement->quantity);
        $this->assertSame(7, $movement->balance_after);
    }

    public function test_decrement_refuses_to_go_below_zero(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $variant->update(['stock' => 2]);

        $this->expectException(InsufficientStockException::class);

        try {
            app(InventoryService::class)->decrement($variant, 5);
        } finally {
            $this->assertSame(2, $variant->fresh()->stock);
        }
    }

    public function test_adjust_sets_absolute_level_and_logs_delta(): void
    {
        $variant = $this->variantFor($this->activeVendor());
        $variant->update(['stock' => 8]);

        $movement = app(InventoryService::class)->adjust($variant, 5, 'Stock count');

        $this->assertSame(5, $variant->fresh()->stock);
        $this->assertSame('adjustment', $movement->type);
        $this->assertSame(-3, $movement->quantity);

        $this->assertNull(app(InventoryService::class)->adjust($variant->fresh(), 5, 'no-op'));
    }

    public function test_checkout_decrements_stock_and_uses_server_side_price(): void
    {
        $vendor = $this->activeVendor();
        $variant = $this->variantFor($vendor);
        $variant->update(['stock' => 5, 'price' => 10000, 'discount_price' => null]);

        $customer = $this->verifiedCustomer();

        $cart = [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => 1, // tampered client price, must be ignored
                'quantity' => 2,
            ],
        ];

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $cart])
            ->post(route('checkout.store'), [
                'first_name' => 'Inv',
                'last_name' => 'Buyer',
                'address' => '1 Test St',
                'region' => 'Dar es Salaam',
                'city' => 'Dar es Salaam',
                'email' => $customer->email,
                'phone' => '0700000000',
            ])
            ->assertRedirect(route('thankyou'));

        $this->assertSame(3, $variant->fresh()->stock);

        $detail = \App\Models\OrderDetail::where('variant_id', $variant->id)->latest('id')->first();
        $this->assertNotNull($detail);
        $this->assertSame('10000.00', $detail->price);

        $this->assertDatabaseHas('inventory_movements', [
            'variant_id' => $variant->id,
            'type' => 'sale',
            'quantity' => -2,
            'balance_after' => 3,
        ]);
    }

    public function test_checkout_blocks_overselling_and_rolls_back(): void
    {
        $vendor = $this->activeVendor();
        $variant = $this->variantFor($vendor);
        $variant->update(['stock' => 1]);

        $customer = $this->verifiedCustomer();
        $cart = [
            'cart_'.$variant->product_id.'_' => [
                'product_id' => $variant->product_id,
                'variant_id' => $variant->id,
                'price' => (float) $variant->price,
                'quantity' => 2,
            ],
        ];

        $before = Order::count();

        $this->actingAs($customer, 'customer')
            ->withSession(['cart' => $cart])
            ->post(route('checkout.store'), [
                'first_name' => 'Inv',
                'address' => '1 Test St',
                'region' => 'Dar es Salaam',
                'city' => 'Dar es Salaam',
                'email' => $customer->email,
                'phone' => '0700000000',
            ])
            ->assertRedirect(route('cart.view'));

        $this->assertSame(1, $variant->fresh()->stock);
        $this->assertSame($before, Order::count());
        $this->assertFalse(
            InventoryMovement::where('variant_id', $variant->id)->where('type', 'sale')->exists()
        );
    }

    public function test_vendor_cannot_adjust_another_vendors_stock(): void
    {
        $owner = $this->activeVendor();
        $variant = $this->variantFor($owner);

        $rival = Vendor::create([
            'name' => 'Rival',
            'shop_name' => 'Rival Shop',
            'email' => 'rival-stock-'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'status' => 'active',
            'is_approved' => 1,
        ]);

        $this->actingAs($rival, 'vendor')
            ->post(route('vendor.products.inventory.adjust', $variant->product_id), [
                'variant_id' => $variant->id,
                'quantity' => 0,
            ])
            ->assertNotFound();
    }
}
