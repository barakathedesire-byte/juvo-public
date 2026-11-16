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

class SecurityXssRegressionTest extends TestCase
{
    use DatabaseTransactions;

    protected const PAYLOAD = '<script>alert(document.cookie)</script><img src=x onerror=alert(1)>';

    protected function vendorWithStore(): array
    {
        $vendor = Vendor::create([
            'name' => 'Store Owner',
            'shop_name' => 'Store Owner Shop',
            'email' => uniqid('owner').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner Store',
            'slug' => 'store-'.uniqid(),
            'status' => 'active',
        ]);

        return [$vendor, $shop];
    }

    protected function productFor(Vendor $vendor, Shop $shop, string $description): Product
    {
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'p-'.uniqid(),
            'category_id' => Category::first()->id,
            'brand_id' => null,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);

        $product->translation()->create([
            'language_code' => 'en',
            'name' => 'Store Product',
            'description' => $description,
        ]);

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

    public function test_product_description_is_stored_xss_safe_on_public_page(): void
    {
        [$vendor, $shop] = $this->vendorWithStore();
        $product = $this->productFor($vendor, $shop, self::PAYLOAD);

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(document.cookie)</script>', $html);
        $this->assertStringNotContainsString('onerror=alert(1)', $html);
    }

    public function test_page_content_is_stored_xss_safe(): void
    {
        $page = \App\Models\Page::create([
            'slug' => 'xss-'.uniqid(),
            'page_type' => 'custom',
            'template' => 'content',
            'status' => 1,
        ]);

        $page->translations()->create([
            'language_code' => 'en',
            'title' => 'Evil',
            'content' => self::PAYLOAD,
        ]);

        $html = $this->get(route('page.show', $page->slug))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(document.cookie)</script>', $html);
        $this->assertStringNotContainsString('onerror=alert(1)', $html);
    }
}
