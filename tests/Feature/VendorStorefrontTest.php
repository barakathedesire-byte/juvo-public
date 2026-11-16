<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorStorefrontTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendorWithStore(array $vendorAttrs = [], array $shopAttrs = []): array
    {
        $vendor = Vendor::create(array_merge([
            'name' => 'Store Owner',
            'shop_name' => 'Store Owner Shop',
            'email' => uniqid('owner').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ], $vendorAttrs));

        $shop = Shop::create(array_merge([
            'vendor_id' => $vendor->id,
            'name' => 'Owner Store',
            'slug' => 'store-'.uniqid(),
            'status' => 'active',
        ], $shopAttrs));

        return [$vendor, $shop];
    }

    protected function productFor(Vendor $vendor, Shop $shop, array $attrs = []): Product
    {
        $product = Product::create(array_merge([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'p-'.uniqid(),
            'category_id' => Category::first()->id,
            'brand_id' => null,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ], $attrs));
        $product->translation()->create(['language_code' => 'en', 'name' => 'Store Product', 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 5000.00,
            'stock' => 5,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product;
    }

    public function test_public_storefront_and_subpages_render(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();
        $this->productFor($vendor, $shop);

        foreach (['store.show', 'store.products', 'store.collections', 'store.about'] as $route) {
            $this->get(route($route, $shop->slug))->assertOk();
        }
    }

    public function test_storefront_products_page_filters_by_category(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();
        $product = $this->productFor($vendor, $shop);
        $category = Category::find($product->category_id);

        $this->get(route('store.products', [$shop->slug, 'category' => $category->slug]))
            ->assertOk();
    }

    public function test_suspended_vendor_store_is_not_public(): void
    {
        [$vendor, $shop] = $this->vendorWithStore(['status' => 'suspended']);
        $this->productFor($vendor, $shop);

        $this->get(route('store.show', $shop->slug))->assertNotFound();
    }

    public function test_vendor_can_update_own_store_profile(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();

        $this->actingAs($vendor, 'vendor')->patch(route('vendor.store.update'), [
            'name' => 'Renamed Store',
            'slug' => 'renamed-'.uniqid(),
            'description' => 'A great store',
            'story' => 'Our story',
            'support_email' => 'help@example.com',
            'vacation_mode' => 1,
            'vacation_note' => 'Back soon',
        ])->assertRedirect(route('vendor.store.edit'));

        $shop->refresh();
        $this->assertSame('Renamed Store', $shop->name);
        $this->assertSame('A great store', $shop->description);
        $this->assertSame('Our story', $shop->story);
        $this->assertTrue($shop->vacation_mode);
        $this->assertSame('Back soon', $shop->vacation_note);
    }

    public function test_store_update_does_not_affect_other_vendors(): void
    {
        [$vendorA, $shopA] = $this->vendorWithStore();
        [$vendorB, $shopB] = $this->vendorWithStore();

        $this->actingAs($vendorA, 'vendor')->patch(route('vendor.store.update'), [
            'name' => 'A Store',
        ])->assertRedirect(route('vendor.store.edit'));

        $this->assertSame('A Store', $shopA->refresh()->name);
        $this->assertSame('Owner Store', $shopB->refresh()->name);
    }

    public function test_vendor_collections_are_scoped_to_vendor_on_storefront(): void
    {
        [$vendorA, $shopA] = $this->vendorWithStore();
        [$vendorB, $shopB] = $this->vendorWithStore();
        $this->productFor($vendorA, $shopA);

        \App\Models\Collection::create([
            'vendor_id' => $vendorA->id,
            'name' => 'A Picks',
            'slug' => 'a-picks-'.uniqid(),
            'rule_type' => 'all',
            'status' => 'active',
        ]);
        \App\Models\Collection::create([
            'vendor_id' => $vendorB->id,
            'name' => 'B Picks',
            'slug' => 'b-picks-'.uniqid(),
            'rule_type' => 'all',
            'status' => 'active',
        ]);

        $this->get(route('store.collections', $shopA->slug))
            ->assertOk()
            ->assertSee('A Picks')
            ->assertDontSee('B Picks');
    }

    public function test_vendor_store_builder_route_is_removed(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('vendor.store.builder'));

        [$vendor] = $this->vendorWithStore();

        $this->actingAs($vendor, 'vendor')
            ->get('/vendor/store/builder')
            ->assertNotFound();
    }

    public function test_shop_published_layout_renders_on_storefront(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();

        $shop->layout = [
            ['id' => 'a1', 'type' => 'heading', 'hidden' => false, 'settings' => ['text' => 'Welcome to my store']],
            ['id' => 'a2', 'type' => 'not_a_real_block', 'hidden' => false, 'settings' => []],
        ];
        $shop->layout_published_at = now();
        $shop->save();

        $this->assertNotEmpty($shop->refresh()->layout);
        $this->assertNotNull($shop->layout_published_at);

        $this->get(route('store.show', $shop->slug))
            ->assertOk()
            ->assertSee('Welcome to my store');
    }

    public function test_published_layout_only_shows_own_products(): void
    {
        [$vendorA, $shopA] = $this->vendorWithStore();
        [$vendorB, $shopB] = $this->vendorWithStore();
        $this->productFor($vendorA, $shopA, ['slug' => 'mine-'.uniqid()]);
        $this->productFor($vendorB, $shopB, ['slug' => 'theirs-'.uniqid()]);

        $shopA->layout = [
            ['id' => 'g1', 'type' => 'product_grid', 'hidden' => false, 'settings' => ['title' => 'Our Products', 'source' => 'new_arrivals', 'limit' => 8]],
        ];
        $shopA->layout_published_at = now();
        $shopA->save();

        $vendorBProduct = \App\Models\Product::where('vendor_id', $vendorB->id)->first();

        $this->get(route('store.show', $shopA->slug))
            ->assertOk()
            ->assertDontSee($vendorBProduct->slug);
    }

    public function test_storefront_restores_default_when_layout_cleared(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();

        $shop->layout = [['id' => 'x', 'type' => 'heading', 'hidden' => false, 'settings' => ['text' => 'X']]];
        $shop->layout_published_at = now();
        $shop->save();

        $shop->layout = null;
        $shop->layout_published_at = null;
        $shop->save();

        $this->get(route('store.show', $shop->slug))->assertOk();

        $this->assertNull($shop->refresh()->layout);
        $this->assertNull($shop->layout_published_at);
    }
}
