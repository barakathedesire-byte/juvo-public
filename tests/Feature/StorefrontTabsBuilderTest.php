<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\StorefrontTemplate;
use App\Models\Vendor;
use App\Services\Store\StoreBlocks;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class StorefrontTabsBuilderTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::where('is_approved', 1)->orderBy('id')->firstOrFail();
    }

    protected function heading(string $text): array
    {
        $block = StoreBlocks::make('heading');
        $block['settings']['text'] = $text;

        return $block;
    }

    protected function globalTemplate(array $blocks): StorefrontTemplate
    {
        return StorefrontTemplate::create([
            'name' => 'Global tabs',
            'scope' => StorefrontTemplate::SCOPE_GLOBAL,
            'blocks' => $blocks,
            'status' => 1,
            'published_at' => now(),
        ]);
    }

    public function test_builder_edit_page_exposes_every_region_tab(): void
    {
        $template = $this->globalTemplate([]);

        $this->actingAs($this->admin())
            ->get(route('admin.storefront.edit', $template->id))
            ->assertOk()
            ->assertSee('Products')
            ->assertSee('Collections')
            ->assertSee('Shipping')
            ->assertSee('Returns');
    }

    public function test_admin_can_save_blocks_for_each_region(): void
    {
        $template = $this->globalTemplate([]);

        $this->actingAs($this->admin())
            ->postJson(route('admin.storefront.save', $template->id), [
                'regions' => json_encode([
                    'home' => [$this->heading('Home Hero')],
                    'products' => [$this->heading('Products Intro')],
                    'about' => [
                        $this->heading('Our Story'),
                        ['id' => 'bad', 'type' => 'nope', 'settings' => []],
                    ],
                ]),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 3]);

        $template->refresh();

        $this->assertSame('Home Hero', $template->blocksFor('home')[0]['settings']['text']);
        $this->assertSame('Products Intro', $template->blocksFor('products')[0]['settings']['text']);
        $this->assertCount(1, $template->blocksFor('about'));
        $this->assertSame([], $template->blocksFor('shipping'));
    }

    public function test_products_tab_renders_its_own_region_blocks(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $this->globalTemplate([
            'products' => [$this->heading('Products Tab Intro')],
            'about' => [$this->heading('About Tab Intro')],
        ]);

        $this->get(route('store.products', $shop->slug))
            ->assertOk()
            ->assertSee('Products Tab Intro')
            ->assertDontSee('About Tab Intro');
    }

    public function test_region_blocks_do_not_leak_into_the_home_tab(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $this->globalTemplate([
            'products' => [$this->heading('Products Only')],
        ]);

        $this->get(route('store.show', $shop->slug))
            ->assertOk()
            ->assertDontSee('Products Only');
    }

    public function test_vendor_region_layout_overrides_the_template_for_that_tab(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();

        $this->globalTemplate([
            'products' => [$this->heading('Admin Products Intro')],
            'about' => [$this->heading('Admin About Intro')],
        ]);

        $shop->update([
            'layout' => [
                'products' => [$this->heading('Vendor Products Intro')],
            ],
        ]);

        $html = $this->get(route('store.products', $shop->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Vendor Products Intro', $html);
        $this->assertStringNotContainsString('Admin Products Intro', $html);

        $this->get(route('store.about', $shop->slug))
            ->assertOk()
            ->assertSee('Admin About Intro');
    }

    public function test_legacy_flat_blocks_are_treated_as_the_home_region(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $this->globalTemplate([$this->heading('Legacy Home Heading')]);

        $this->get(route('store.show', $shop->slug))
            ->assertOk()
            ->assertSee('Legacy Home Heading');

        $this->get(route('store.products', $shop->slug))
            ->assertOk()
            ->assertDontSee('Legacy Home Heading');
    }

    public function test_legacy_flat_shop_layout_is_treated_as_home(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();

        $shop->update(['layout' => [$this->heading('Flat Vendor Home')]]);

        $this->assertSame('Flat Vendor Home', $shop->fresh()->layoutFor('home')[0]['settings']['text']);
        $this->assertSame([], $shop->fresh()->layoutFor('products'));
    }

    public function test_multi_row_blocks_are_available_in_the_store_builder(): void
    {
        $this->assertContains('product_rows', StoreBlocks::TYPES);
        $this->assertContains('countdown', StoreBlocks::TYPES);
        $this->assertNotContains('html', StoreBlocks::TYPES);

        $clean = StoreBlocks::sanitize([
            ['id' => 'r1', 'type' => 'product_rows', 'settings' => ['rows' => [['title' => 'Row', 'source' => 'featured']]]],
        ]);

        $this->assertCount(1, $clean);
        $this->assertSame('product_rows', $clean[0]['type']);
    }

    public function test_preview_accepts_a_region_parameter(): void
    {
        $template = $this->globalTemplate([
            'about' => [$this->heading('Preview About')],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.storefront.preview', ['template' => $template->id, 'region' => 'about']))
            ->assertOk()
            ->assertSee('Preview About');
    }
}
