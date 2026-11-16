<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CartTest extends TestCase
{
    use DatabaseTransactions;

    protected function variantWithStock(int $stock): ProductVariant
    {
        $product = Product::visible()->with('variants')->where('product_type', 'simple')->first()
            ?? Product::visible()->with('variants')->first();

        $variant = $product->variants()->where('is_primary', 1)->first()
            ?? $product->variants()->first();

        $variant->update(['stock' => $stock]);

        return $variant->fresh();
    }

    public function test_add_to_cart_creates_a_line_and_counts_units(): void
    {
        $variant = $this->variantWithStock(5);

        $response = $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 2,
        ])->assertOk();

        $cart = session('cart');
        $this->assertCount(1, $cart);
        $this->assertSame(2, (int) session('cart_count'));
        $this->assertSame(2, $response->json('cart_count'));
    }

    public function test_add_to_cart_rejects_quantity_beyond_available_stock(): void
    {
        $variant = $this->variantWithStock(3);

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 2,
        ])->assertOk();

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 2,
        ])->assertStatus(422);

        $this->assertSame(2, (int) session('cart_count'));
    }

    public function test_add_to_cart_rejects_an_out_of_stock_variant(): void
    {
        $variant = $this->variantWithStock(0);

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    public function test_update_cart_changes_quantity_by_line_key(): void
    {
        $variant = $this->variantWithStock(10);

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 1,
        ])->assertOk();

        $key = array_key_first(session('cart'));

        $this->postJson(route('cart.update'), [
            'cart' => [['key' => $key, 'quantity' => 4]],
        ])->assertOk();

        $this->assertSame(4, (int) session('cart')[$key]['quantity']);
        $this->assertSame(4, (int) session('cart_count'));
    }

    public function test_update_cart_clamps_quantity_to_stock(): void
    {
        $variant = $this->variantWithStock(3);

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 1,
        ])->assertOk();

        $key = array_key_first(session('cart'));

        $this->postJson(route('cart.update'), [
            'cart' => [['key' => $key, 'quantity' => 99]],
        ])->assertOk();

        $this->assertSame(3, (int) session('cart')[$key]['quantity']);
    }

    public function test_remove_from_cart_deletes_the_line_and_recounts(): void
    {
        $variant = $this->variantWithStock(5);

        $this->postJson(route('cart.add'), [
            'product_id' => $variant->product_id,
            'quantity' => 2,
        ])->assertOk();

        $key = array_key_first(session('cart'));

        $this->postJson(route('cart.remove'), ['key' => $key])->assertOk();

        $this->assertSame([], session('cart'));
        $this->assertSame(0, (int) session('cart_count'));
    }
}
