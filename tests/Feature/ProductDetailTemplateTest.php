<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Product;
use App\Services\Pages\PageRegistry;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ProductDetailTemplateTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function productSystemPage(): Page
    {
        app(PageRegistry::class)->ensureSystemPages();

        $page = Page::where('system_key', 'product')->first();
        $this->assertNotNull($page);

        return $page;
    }

    public function test_product_page_defaults_to_the_marketplace_template(): void
    {
        $product = Product::visible()->firstOrFail();

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('data-product-template="marketplace"', false);
    }

    public function test_admin_can_switch_the_product_page_to_the_classic_template(): void
    {
        $page = $this->productSystemPage();
        $product = Product::visible()->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.pages.saveBlocks', $page->id), [
                'template' => 'classic',
                'blocks' => $page->blocks ?? [],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame('classic', $page->refresh()->template);

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('data-product-template="classic"', false);
    }

    public function test_invalid_product_template_is_rejected(): void
    {
        $page = $this->productSystemPage();
        $before = $page->refresh()->template;

        $this->actingAs($this->admin())
            ->postJson(route('admin.pages.saveBlocks', $page->id), [
                'template' => 'does-not-exist',
                'blocks' => [],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('template');

        $this->assertSame($before, $page->refresh()->template);
    }

    public function test_classic_and_marketplace_templates_share_the_same_product_data(): void
    {
        $product = Product::visible()->firstOrFail();

        $marketplace = $this->get(route('product.show', $product->slug))->assertOk();
        $name = optional($product->translation)->name ?? $product->slug;

        $page = $this->productSystemPage();
        $this->actingAs($this->admin())
            ->post(route('admin.pages.saveBlocks', $page->id), ['template' => 'classic'])
            ->assertOk();

        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee($name, false);

        $this->assertSame('classic', $page->refresh()->template);
        $this->assertStringContainsString('data-product-template="marketplace"', $marketplace->getContent());
    }

    public function test_appearance_settings_persist_and_hide_components(): void
    {
        $page = $this->productSystemPage();
        $product = Product::visible()->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('admin.pages.saveBlocks', $page->id), [
                'template' => 'marketplace',
                'blocks' => $page->blocks ?? [],
                'appearance' => [
                    'show_seller_card' => false,
                    'show_delivery_panel' => false,
                    'show_sponsored' => false,
                    'show_share' => false,
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $saved = $page->refresh()->settings;
        $this->assertFalse($saved['show_seller_card']);
        $this->assertFalse($saved['show_delivery_panel']);
        $this->assertTrue($saved['show_breadcrumb']);

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringNotContainsString('SELLER INFORMATION', $html);
        $this->assertStringNotContainsString('DELIVERY & RETURNS', $html);
        $this->assertStringNotContainsString('SHARE THIS PRODUCT', $html);
    }

    public function test_default_appearance_shows_seller_and_delivery_panels(): void
    {
        $product = Product::visible()->firstOrFail();

        $html = $this->get(route('product.show', $product->slug))->assertOk()->getContent();
        $this->assertStringContainsString('SELLER INFORMATION', $html);
        $this->assertStringContainsString('DELIVERY & RETURNS', $html);
    }
}
