<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Models\HomepageTile;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class HomepageBuilderTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_builder_overview_allows_admin_and_blocks_other_roles(): void
    {
        $this->actingAs($this->admin())->get('/admin/homepage')->assertOk();

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get('/admin/homepage')->assertForbidden();
    }

    public function test_published_slide_becomes_visible_and_draft_stays_hidden(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/slides', [
            'title' => 'Visible Hero Slide',
            'cta_label' => 'Shop',
            'cta_url' => '/products',
            'status' => 'published',
        ])->assertRedirect();

        $this->actingAs($this->admin())->post('/admin/homepage/slides', [
            'title' => 'Hidden Draft Slide',
            'status' => 'draft',
        ])->assertRedirect();

        $this->assertDatabaseHas('homepage_slides', ['title' => 'Visible Hero Slide']);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('Visible Hero Slide', $html);
        $this->assertStringNotContainsString('Hidden Draft Slide', $html);
    }

    public function test_slide_appearance_settings_render_on_the_storefront(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/slides', [
            'title' => 'Big Colourful Banner',
            'status' => 'published',
            'height' => 'large',
            'title_size' => 56,
            'bg_color' => '#112233',
            'bg_color_2' => '#445566',
            'text_color' => '#ffcc00',
            'overlay' => 'dark',
        ])->assertRedirect();

        $this->assertDatabaseHas('homepage_slides', [
            'title' => 'Big Colourful Banner',
            'height' => 'large',
            'overlay' => 'dark',
        ]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('jv-hero-h-large', $html);
        $this->assertStringContainsString('jv-hero-ov-dark', $html);
        $this->assertStringContainsString('--jv-hero-title-size: 56px', $html);
        $this->assertStringContainsString('linear-gradient(120deg, #112233 0%, #445566 100%)', $html);
    }

    public function test_slide_appearance_rejects_bad_input(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/slides', [
            'title' => 'Bad Banner',
            'status' => 'published',
            'height' => 'enormous',
            'bg_color' => 'not-a-colour',
            'overlay' => 'sideways',
        ])->assertSessionHasErrors(['height', 'bg_color', 'overlay']);
    }

    public function test_draft_wins_in_preview_but_not_on_public_site(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/slides', [
            'title' => 'Preview Only Slide',
            'status' => 'draft',
        ])->assertRedirect();

        $this->actingAs($this->admin())->get('/admin/homepage/preview')
            ->assertOk()
            ->assertSee('Preview Only Slide');

        $this->get('/')->assertOk()->assertDontSee('Preview Only Slide');
    }

    public function test_tile_create_update_reorder_and_toggle(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/homepage/tiles', [
            'title' => 'Just Landed',
            'subtitle' => 'Fresh drops',
            'link_type' => 'collection',
            'link_value' => 'new_arrivals',
            'status' => 'published',
        ])->assertRedirect();

        $tile = HomepageTile::where('title', 'Just Landed')->firstOrFail();

        $this->actingAs($admin)->put("/admin/homepage/tiles/{$tile->id}", [
            'title' => 'Just Landed Now',
            'link_type' => 'url',
            'link_value' => '/products',
            'status' => 'published',
        ])->assertRedirect();
        $this->assertSame('Just Landed Now', $tile->fresh()->title);

        $this->actingAs($admin)->post('/admin/homepage/tiles/reorder', [
            'order' => [$tile->id],
        ])->assertOk();
        $this->assertSame(1, $tile->fresh()->sort_order);

        $this->actingAs($admin)->post("/admin/homepage/tiles/{$tile->id}/toggle")->assertRedirect();
        $this->assertSame('draft', $tile->fresh()->status);

        $this->actingAs($admin)->delete("/admin/homepage/tiles/{$tile->id}")->assertRedirect();
        $this->assertDatabaseMissing('homepage_tiles', ['id' => $tile->id]);
    }

    public function test_tile_requires_category_value(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/tiles', [
            'title' => 'Bad Tile',
            'link_type' => 'category',
            'link_value' => '',
            'status' => 'published',
        ])->assertSessionHasErrors('link_value');
    }

    public function test_section_create_and_category_validation(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/homepage/sections', [
            'type' => 'product_carousel',
            'title' => 'Trending Now',
            'source' => 'trending',
            'layout' => 'carousel',
            'limit' => 8,
            'status' => 'published',
        ])->assertRedirect();
        $this->assertDatabaseHas('homepage_sections', ['title' => 'Trending Now']);

        $this->actingAs($admin)->post('/admin/homepage/sections', [
            'type' => 'product_grid',
            'title' => 'Broken Category',
            'source' => 'category',
            'status' => 'published',
        ])->assertSessionHasErrors('category_id');
    }

    public function test_promo_grid_section_stores_cards(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'promo_grid',
            'title' => 'Hot Promotions',
            'status' => 'published',
            'cards' => [
                ['title' => 'Big Deal', 'subtitle' => 'Save big', 'url' => '/products', 'size' => 'lg', 'theme' => 'orange'],
                ['title' => 'Small Deal', 'url' => '/products', 'size' => 'sm', 'theme' => 'light'],
            ],
        ])->assertRedirect();

        $section = HomepageSection::where('title', 'Hot Promotions')->firstOrFail();
        $this->assertCount(2, $section->settings['cards']);
        $this->assertSame('Big Deal', $section->settings['cards'][0]['title']);
        $this->assertSame('promo', $section->source);
    }

    public function test_settings_can_be_updated(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/settings', [
            'autoplay' => '1',
            'autoplay_speed' => '7000',
            'show_category_strip' => '0',
        ])->assertRedirect();

        $this->assertSame('7000', \App\Models\HomepageSetting::get('autoplay_speed'));
        $this->assertSame('0', \App\Models\HomepageSetting::get('show_category_strip'));
    }

    public function test_reorder_requires_existing_ids(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/slides/reorder', [
            'order' => [999999],
        ])->assertSessionHasErrors('order.0');
    }
}
