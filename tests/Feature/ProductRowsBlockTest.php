<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ProductRowsBlockTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendorShop(): array
    {
        $vendor = Vendor::create([
            'name' => 'Rows Vendor',
            'shop_name' => 'Rows Vendor Shop',
            'email' => uniqid('rows').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Rows Shop',
            'slug' => 'rows-'.uniqid(),
            'status' => 'active',
        ]);

        return [$vendor, $shop];
    }

    protected function makeProduct(Vendor $vendor, Shop $shop, string $name, float $price, ?float $discountPrice = null): Product
    {
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'row-p-'.uniqid(),
            'category_id' => Category::first()->id,
            'brand_id' => null,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);

        $product->translation()->create(['language_code' => 'en', 'name' => $name, 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => $price,
            'discount_price' => $discountPrice,
            'stock' => 5,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product;
    }

    protected function pageWithRows(array $rows, array $settings = []): Page
    {
        $page = Page::create([
            'slug' => 'rows-'.uniqid(),
            'status' => 1,
            'page_type' => 'custom',
            'template' => 'default',
            'is_system' => false,
            'blocks' => [[
                'id' => 'rows1',
                'type' => 'product_rows',
                'settings' => array_merge(['title' => 'MerchRowsXYZ'], $settings, ['rows' => $rows]),
            ]],
        ]);

        $page->translations()->create(['language_code' => 'en', 'title' => 'Merch Rows', 'content' => '<p>x</p>']);

        return $page;
    }

    public function test_product_rows_block_is_sanitised_when_saved(): void
    {
        $this->actingAs($this->admin());
        $shopPage = Page::where('system_key', 'shop')->first();
        $this->assertNotNull($shopPage);

        $this->post("/admin/pages/{$shopPage->id}/save-blocks", [
            'blocks' => [[
                'id' => 'r1',
                'type' => 'product_rows',
                'settings' => [
                    'title' => 'SavedRowsXYZ',
                    'rows' => [[
                        'title' => 'Row one',
                        'source' => 'manual',
                        'limit' => 6,
                        'product_ids' => '1,2,3',
                        'visible' => '1',
                        'bogus_key' => 'should-be-stripped',
                    ]],
                ],
            ]],
        ])->assertOk()->assertJson(['success' => true]);

        $block = collect($shopPage->fresh()->blocks)->firstWhere('type', 'product_rows');
        $this->assertNotNull($block);

        $row = $block['settings']['rows'][0];
        $this->assertSame('Row one', $row['title']);
        $this->assertSame('manual', $row['source']);
        $this->assertSame(6, $row['limit']);
        $this->assertTrue($row['visible']);
        $this->assertArrayNotHasKey('bogus_key', $row);
    }

    public function test_manual_row_renders_only_selected_products(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $chosen = $this->makeProduct($vendor, $shop, 'RowAlphaUniqueProd', 5000);
        $this->makeProduct($vendor, $shop, 'RowBetaUniqueProd', 6000);

        $page = $this->pageWithRows([[
            'title' => 'Handpicked',
            'source' => 'manual',
            'product_ids' => (string) $chosen->id,
            'limit' => 8,
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('Handpicked')
            ->assertSee('RowAlphaUniqueProd')
            ->assertDontSee('RowBetaUniqueProd');
    }

    public function test_hidden_row_is_not_rendered(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'VisibleProdXYZ', 5000);

        $page = $this->pageWithRows([
            ['title' => 'ShownRowXYZ', 'source' => 'manual', 'product_ids' => (string) $product->id, 'visible' => true],
            ['title' => 'HiddenRowXYZ', 'source' => 'manual', 'product_ids' => (string) $product->id, 'visible' => false],
        ]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('ShownRowXYZ')
            ->assertDontSee('HiddenRowXYZ');
    }

    public function test_empty_row_with_empty_state_shows_message(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $this->makeProduct($vendor, $shop, 'WhateverProdXYZ', 5000);

        $page = $this->pageWithRows([[
            'title' => 'ComingSoon',
            'source' => 'manual',
            'product_ids' => '',
            'empty_state' => 'EmptyStateMsgXYZ',
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('EmptyStateMsgXYZ');
    }

    public function test_empty_row_without_empty_state_is_omitted(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $this->makeProduct($vendor, $shop, 'ProdXYZ2', 5000);

        $page = $this->pageWithRows([[
            'title' => 'GhostRow',
            'source' => 'manual',
            'product_ids' => '',
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertDontSee('MerchRowsXYZ');
    }

    public function test_price_asc_sort_orders_row_products(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $expensive = $this->makeProduct($vendor, $shop, 'PriceHighProd', 9000);
        $cheap = $this->makeProduct($vendor, $shop, 'PriceLowProd', 1000);

        $page = $this->pageWithRows([[
            'title' => 'Cheap first',
            'source' => 'manual',
            'product_ids' => $expensive->id.','.$cheap->id,
            'sort' => 'price_asc',
            'limit' => 8,
            'visible' => true,
        ]]);

        $html = $this->get('/page/'.$page->slug)->assertOk()->getContent();

        $lowPos = strpos($html, 'PriceLowProd');
        $highPos = strpos($html, 'PriceHighProd');
        $this->assertNotFalse($lowPos);
        $this->assertNotFalse($highPos);
        $this->assertLessThan($highPos, $lowPos);
    }

    public function test_sponsored_row_is_empty_without_eligible_campaign(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $this->makeProduct($vendor, $shop, 'SponsoredProdXYZ', 5000);

        $page = $this->pageWithRows([[
            'title' => 'SponsoredRowCfg',
            'source' => 'sponsored',
            'limit' => 6,
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertDontSee('MerchRowsXYZ');
    }

    public function test_grid_layout_row_renders_products(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'GridProdXYZ', 5000);

        $page = $this->pageWithRows([[
            'title' => 'GridRowXYZ',
            'source' => 'manual',
            'product_ids' => (string) $product->id,
            'layout' => 'grid',
            'columns_desktop' => 4,
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('GridRowXYZ')
            ->assertSee('GridProdXYZ')
            ->assertSee('jv-row-grid', false);
    }

    public function test_vendor_row_scopes_products_to_one_vendor(): void
    {
        [$vendorA, $shopA] = $this->vendorShop();
        $this->makeProduct($vendorA, $shopA, 'VendorAUniqueProd', 5000);

        [$vendorB, $shopB] = $this->vendorShop();
        $this->makeProduct($vendorB, $shopB, 'VendorBUniqueProd', 5000);

        $page = $this->pageWithRows([[
            'title' => 'FromVendorA',
            'source' => 'vendor',
            'vendor_id' => $vendorA->id,
            'limit' => 8,
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('VendorAUniqueProd')
            ->assertDontSee('VendorBUniqueProd');
    }

    public function test_flash_sale_row_lists_only_discounted_products(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $this->makeProduct($vendor, $shop, 'DiscountedProdXYZ', 10000, 4000);
        $this->makeProduct($vendor, $shop, 'FullPriceProdXYZ', 10000);

        $page = $this->pageWithRows([[
            'title' => 'FlashRowXYZ',
            'source' => 'flash_sale',
            'limit' => 8,
            'visible' => true,
        ]]);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('DiscountedProdXYZ')
            ->assertDontSee('FullPriceProdXYZ');
    }

    public function test_recently_viewed_row_shows_products_viewed_by_the_shopper(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'RecentlyViewedProdXYZ', 5000);

        $customer = \App\Models\Customer::create([
            'name' => 'Rows Shopper',
            'email' => 'rows-shopper-'.uniqid().'@example.com',
            'password' => 'password123',
            'status' => 1,
        ]);

        $this->actingAs($customer, 'customer')->get('/product/'.$product->slug)->assertOk();

        $page = $this->pageWithRows([[
            'title' => 'RecentRowXYZ',
            'source' => 'recently_viewed',
            'limit' => 8,
            'visible' => true,
        ]]);

        $this->actingAs($customer, 'customer')
            ->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('RecentRowXYZ')
            ->assertSee('RecentlyViewedProdXYZ');
    }
}
