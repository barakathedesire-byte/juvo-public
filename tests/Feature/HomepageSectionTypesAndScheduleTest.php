<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class HomepageSectionTypesAndScheduleTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_picture_section_renders_with_card_colours_and_schedule(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'image',
            'title' => 'Big Picture Promo',
            'status' => 'published',
            'image_url' => 'https://example.com/promo.jpg',
            'image_link' => '/products',
            'image_alt' => 'Promo alt',
            'image_rounded' => '1',
            'card_enabled' => '1',
            'bg_style' => 'split',
            'bg_color' => '#112233',
            'bg_color_2' => '#445566',
            'text_color' => '#ffffff',
            'accent_color' => '#ffcc00',
            'card_edges' => 'pill',
            'card_shape' => 'pill',
            'starts_at' => now()->subHour()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'auto_delete' => '1',
        ])->assertRedirect();

        $section = HomepageSection::where('title', 'Big Picture Promo')->firstOrFail();

        $this->assertTrue($section->auto_delete);
        $this->assertTrue($section->isLive());

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('jv-section-card--edges-pill', $html);
        $this->assertStringContainsString('--jv-card-bg:#112233', $html);
        $this->assertStringContainsString('linear-gradient(90deg,#112233 0 55%,#445566 55% 100%)', $html);
        $this->assertStringContainsString('alt="Promo alt"', $html);
    }

    public function test_announcement_and_slides_sections_render_on_the_storefront(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/homepage/sections', [
            'type' => 'announcement',
            'title' => 'Delivery update',
            'status' => 'published',
            'announcement_body' => 'Orders ship within 24 hours.',
            'announcement_tone' => 'promo',
            'announcement_cta_label' => 'Shop now',
            'announcement_cta_url' => '/products',
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/homepage/sections', [
            'type' => 'slides',
            'title' => 'Featured slides',
            'status' => 'published',
            'banners' => [
                ['title' => 'Slide One', 'subtitle' => 'First', 'image_url' => 'https://example.com/one.jpg', 'url' => '/products'],
                ['title' => 'Slide Two', 'image_url' => 'https://example.com/two.jpg'],
            ],
        ])->assertRedirect();

        $this->actingAs($admin)->post('/admin/homepage/sections', [
            'type' => 'gallery',
            'title' => 'Picture gallery',
            'status' => 'published',
            'banners' => [
                ['title' => 'Gallery Shot', 'image_url' => 'https://example.com/gallery.jpg'],
            ],
        ])->assertRedirect();

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('jv-announcement--promo', $html);
        $this->assertStringContainsString('Orders ship within 24 hours.', $html);
        $this->assertStringContainsString('jv-section-slides', $html);
        $this->assertStringContainsString('Slide One', $html);
        $this->assertStringContainsString('Gallery Shot', $html);
    }

    public function test_expired_sections_are_hidden_and_only_auto_delete_ones_are_removed(): void
    {
        $expiredKeep = HomepageSection::create([
            'type' => 'announcement',
            'title' => 'Expired keep',
            'status' => 'published',
            'ends_at' => now()->subMinute(),
            'settings' => ['body' => 'Old news'],
        ]);

        $expiredDelete = HomepageSection::create([
            'type' => 'announcement',
            'title' => 'Expired delete',
            'status' => 'published',
            'ends_at' => now()->subMinute(),
            'auto_delete' => true,
            'settings' => ['body' => 'Old news'],
        ]);

        $this->assertFalse($expiredKeep->isLive());
        $this->assertSame(1, HomepageSection::purgeExpired());
        $this->assertDatabaseHas('homepage_sections', ['id' => $expiredKeep->id]);
        $this->assertDatabaseMissing('homepage_sections', ['id' => $expiredDelete->id]);
    }

    public function test_lazy_purge_removes_expired_auto_delete_sections(): void
    {
        Cache::flush();

        $section = HomepageSection::create([
            'type' => 'announcement',
            'title' => 'Lazy expired',
            'status' => 'published',
            'ends_at' => now()->subMinute(),
            'auto_delete' => true,
            'settings' => ['body' => 'Old news'],
        ]);

        app(\App\Services\Homepage\HomepageService::class)->sections();

        $this->assertDatabaseMissing('homepage_sections', ['id' => $section->id]);
    }

    public function test_clearing_the_end_time_also_clears_auto_delete(): void
    {
        $section = HomepageSection::create([
            'type' => 'product_carousel',
            'title' => 'Timed rail',
            'status' => 'published',
            'source' => 'featured',
            'layout' => 'carousel',
            'limit' => 8,
            'starts_at' => now(),
            'ends_at' => now()->addDay(),
            'auto_delete' => true,
        ]);

        $this->actingAs($this->admin())->put('/admin/homepage/sections/'.$section->id, [
            'type' => 'product_carousel',
            'title' => 'Timed rail',
            'status' => 'published',
            'source' => 'featured',
            'layout' => 'carousel',
            'limit' => '8',
            'starts_at' => '',
            'ends_at' => '',
            'auto_delete' => '1',
        ])->assertRedirect();

        $section->refresh();

        $this->assertNull($section->starts_at);
        $this->assertNull($section->ends_at);
        $this->assertFalse($section->auto_delete);
    }
}
