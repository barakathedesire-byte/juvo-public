<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\StorefrontTemplate;
use App\Models\Vendor;
use App\Services\Store\StoreBlocks;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class StorefrontBuilderTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::where('is_approved', 1)->orderBy('id')->firstOrFail();
    }

    protected function headingBlock(string $text): array
    {
        $block = StoreBlocks::make('heading');
        $block['settings']['text'] = $text;

        return $block;
    }

    protected function globalTemplate(array $blocks): StorefrontTemplate
    {
        return StorefrontTemplate::create([
            'name' => 'Global default',
            'scope' => StorefrontTemplate::SCOPE_GLOBAL,
            'blocks' => $blocks,
            'status' => 1,
            'published_at' => now(),
        ]);
    }

    public function test_admin_can_open_the_storefront_builder(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.storefront.index'))
            ->assertOk()
            ->assertSee('Storefront Builder');
    }

    public function test_admin_can_create_a_template(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.storefront.store'), [
                'name' => 'My global template',
                'scope' => 'global',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('storefront_templates', [
            'name' => 'My global template',
            'scope' => 'global',
        ]);
    }

    public function test_global_template_renders_on_a_vendor_without_a_layout(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $this->globalTemplate([
            $this->headingBlock('Admin Heading'),
        ]);

        $this->get(route('store.show', $shop->slug))
            ->assertOk()
            ->assertSee('Admin Heading');
    }

    public function test_vendor_layout_takes_precedence_over_the_global_template(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();

        $this->globalTemplate([
            $this->headingBlock('Admin Heading'),
        ]);

        $shop->update([
            'layout' => [
                $this->headingBlock('Vendor Heading'),
            ],
        ]);

        $html = $this->get(route('store.show', $shop->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Vendor Heading', $html);
        $this->assertStringNotContainsString('Admin Heading', $html);
    }

    public function test_vendor_scoped_template_takes_precedence_over_global(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $this->globalTemplate([
            $this->headingBlock('Global Heading'),
        ]);

        StorefrontTemplate::create([
            'name' => 'Vendor template',
            'scope' => StorefrontTemplate::SCOPE_VENDOR,
            'vendor_id' => $vendor->id,
            'blocks' => [
                $this->headingBlock('Vendor Scoped Heading'),
            ],
            'status' => 1,
            'published_at' => now(),
        ]);

        $html = $this->get(route('store.show', $shop->slug))->assertOk()->getContent();
        $this->assertStringContainsString('Vendor Scoped Heading', $html);
        $this->assertStringNotContainsString('Global Heading', $html);
    }

    protected function draftTemplate(): StorefrontTemplate
    {
        return StorefrontTemplate::create([
            'name' => 'Draft template',
            'scope' => StorefrontTemplate::SCOPE_GLOBAL,
            'blocks' => [],
            'status' => 0,
        ]);
    }

    public function test_admin_can_save_a_draft_template(): void
    {
        $template = $this->draftTemplate();

        $this->actingAs($this->admin())
            ->postJson(route('admin.storefront.save', $template->id), [
                'layout' => json_encode([
                    $this->headingBlock('Draft Heading'),
                    ['id' => 'bad', 'type' => 'not_a_real_block', 'hidden' => false, 'settings' => []],
                ]),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 1]);

        $template->refresh();
        $this->assertCount(1, $template->blocks['home']);
        $this->assertSame('heading', $template->blocks['home'][0]['type']);
        $this->assertFalse((bool) $template->status);
    }

    public function test_admin_can_publish_a_template(): void
    {
        $template = $this->draftTemplate();

        $this->actingAs($this->admin())
            ->postJson(route('admin.storefront.publish', $template->id), [
                'layout' => json_encode([$this->headingBlock('Published Heading')]),
            ])
            ->assertOk()
            ->assertJson(['ok' => true, 'count' => 1]);

        $template->refresh();
        $this->assertTrue((bool) $template->status);
        $this->assertNotNull($template->published_at);
    }

    public function test_unpublished_template_no_longer_renders(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::where('vendor_id', $vendor->id)->firstOrFail();
        $shop->update(['layout' => null]);

        $template = $this->globalTemplate([$this->headingBlock('Live Heading')]);

        $this->get(route('store.show', $shop->slug))->assertSee('Live Heading');

        $this->actingAs($this->admin())
            ->postJson(route('admin.storefront.unpublish', $template->id))
            ->assertOk();

        $this->get(route('store.show', $shop->slug))->assertDontSee('Live Heading');
    }

    public function test_admin_can_delete_a_template(): void
    {
        $template = $this->draftTemplate();

        $this->actingAs($this->admin())
            ->deleteJson(route('admin.storefront.destroy', $template->id))
            ->assertOk();

        $this->assertDatabaseMissing('storefront_templates', ['id' => $template->id]);
    }
}
