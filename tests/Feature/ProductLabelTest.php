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

class ProductLabelTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendorShop(): array
    {
        $vendor = Vendor::create([
            'name' => 'Label Vendor',
            'shop_name' => 'Label Vendor Shop',
            'email' => uniqid('label').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Label Shop',
            'slug' => 'label-'.uniqid(),
            'status' => 'active',
        ]);

        return [$vendor, $shop];
    }

    protected function makeProduct(Vendor $vendor, Shop $shop, string $name, float $price, ?float $discount = null, ?string $label = null, ?string $style = null): Product
    {
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'lab-p-'.uniqid(),
            'category_id' => Category::first()->id,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'label' => $label,
            'label_style' => $style,
        ]);

        $product->translation()->create(['language_code' => 'en', 'name' => $name, 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => $price,
            'discount_price' => $discount,
            'stock' => 5,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product;
    }

    protected function pageWithProduct(Product $product): Page
    {
        $page = Page::create([
            'slug' => 'lab-'.uniqid(),
            'status' => 1,
            'page_type' => 'custom',
            'template' => 'default',
            'is_system' => false,
            'blocks' => [[
                'id' => 'rows1',
                'type' => 'product_rows',
                'settings' => [
                    'title' => 'LabelRowXYZ',
                    'rows' => [[
                        'title' => 'Labeled',
                        'source' => 'manual',
                        'product_ids' => (string) $product->id,
                        'limit' => 8,
                        'visible' => true,
                    ]],
                ],
            ]],
        ]);

        $page->translations()->create(['language_code' => 'en', 'title' => 'Label Page', 'content' => '<p>x</p>']);

        return $page;
    }

    public function test_product_card_shows_admin_assigned_label_badge(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'LabeledCardProd', 5000, null, 'Clearance', 'green');
        $page = $this->pageWithProduct($product);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertSee('Clearance')
            ->assertSee('jv-badge-green', false);
    }

    public function test_product_card_without_label_shows_no_label_badge(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'UnlabeledCardProd', 5000);
        $page = $this->pageWithProduct($product);

        $this->get('/page/'.$page->slug)
            ->assertOk()
            ->assertDontSee('jv-badge-green', false)
            ->assertDontSee('jv-badge-dark', false);
    }

    public function test_product_detail_uses_admin_label_instead_of_flash_sale(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'LabeledDetailProd', 10000, 4000, 'Mega Deal', 'dark');

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('Mega Deal')
            ->assertDontSee('Flash Sale');
    }

    public function test_discounted_product_without_label_is_not_called_flash_sale(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $product = $this->makeProduct($vendor, $shop, 'PlainDiscountProd', 10000, 4000);

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertDontSee('Flash Sale');
    }

    public function test_admin_category_select_lists_subcategories(): void
    {
        $parent = Category::create(['parent_category_id' => null, 'slug' => 'lab-parent-'.uniqid(), 'status' => 1]);
        $parent->translations()->create(['language_code' => 'en', 'name' => 'ParentCatXYZ']);

        $child = Category::create(['parent_category_id' => $parent->id, 'slug' => 'lab-child-'.uniqid(), 'status' => 1]);
        $child->translations()->create(['language_code' => 'en', 'name' => 'ChildCatXYZ']);

        $html = $this->actingAs($this->admin())->get(route('admin.products.create'))->assertOk()->getContent();

        $this->assertStringContainsString('ChildCatXYZ', $html);
        $this->assertStringContainsString('<optgroup', $html);
    }
}
