<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PageManagerTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function makePage(array $attrs = []): Page
    {
        $page = Page::create(array_merge([
            'slug' => 'landing-'.uniqid(),
            'status' => 0,
            'page_type' => 'custom',
            'template' => 'default',
            'is_system' => false,
            'blocks' => [],
        ], $attrs));

        $page->translations()->create([
            'language_code' => 'en',
            'title' => 'Landing Page',
            'content' => '<p>hello</p>',
        ]);

        return $page;
    }

    public function test_dashboard_allows_admin_and_blocks_other_roles(): void
    {
        $this->actingAs($this->admin())->get('/admin/pages')->assertOk();

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get('/admin/pages')->assertForbidden();
    }

    public function test_creating_a_page_registers_it_and_opens_the_editor(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/pages', [
                'title' => 'Summer Sale',
                'page_type' => 'promotional',
                'template' => 'default',
                'status' => 1,
            ])
            ->assertRedirect();

        $page = Page::where('slug', 'summer-sale')->first();
        $this->assertNotNull($page);
        $this->assertDatabaseHas('page_translations', ['page_id' => $page->id, 'title' => 'Summer Sale']);
        $this->assertFalse($page->is_system);

        $this->get("/admin/pages/{$page->id}/editor")->assertOk();
        $this->get("/admin/pages/{$page->id}/preview")->assertOk();
        $this->get('/admin/pages/create')->assertOk();
    }

    public function test_slug_conflicts_are_resolved(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        foreach ([1, 2] as $i) {
            $this->post('/admin/pages', [
                'title' => 'Duplicate Slug',
                'page_type' => 'custom',
                'template' => 'default',
                'status' => 1,
            ]);
        }

        $this->assertEquals(2, Page::where('slug', 'like', 'duplicate-slug%')->count());
    }

    public function test_saved_blocks_are_sanitised(): void
    {
        $page = $this->makePage();

        $this->actingAs($this->admin())
            ->post("/admin/pages/{$page->id}/save-blocks", [
                'blocks' => [
                    ['id' => 'b1', 'type' => 'heading', 'settings' => ['text' => 'Hello', 'level' => 'h2']],
                    ['id' => 'b2', 'type' => 'not_a_real_block', 'settings' => []],
                    ['id' => 'b3', 'type' => 'text', 'settings' => ['body' => '<p>ok</p><script>alert(1)</script><img src=x onerror=alert(2)>']],
                ],
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $blocks = $page->fresh()->blocks;
        $this->assertCount(2, $blocks);
        $this->assertEquals(['heading', 'text'], array_column($blocks, 'type'));

        $body = collect($blocks)->firstWhere('type', 'text')['settings']['body'];
        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onerror', $body);
    }

    public function test_publish_makes_page_public_and_draft_hides_it(): void
    {
        $page = $this->makePage(['status' => 0, 'slug' => 'promo-'.uniqid()]);
        $page->update(['blocks' => [
            ['id' => 'b1', 'type' => 'text', 'settings' => ['body' => '<p>UniqueMarker123</p>']],
        ]]);

        $this->get('/page/'.$page->slug)->assertNotFound();

        $this->actingAs($this->admin())->post("/admin/pages/{$page->id}/publish")->assertOk();
        $this->assertEquals(1, (int) $page->fresh()->status);
        $this->assertNotNull($page->fresh()->published_at);

        $this->get('/page/'.$page->slug)->assertOk()->assertSee('UniqueMarker123');

        $this->actingAs($this->admin())->post("/admin/pages/{$page->id}/unpublish")->assertOk();
        $this->get('/page/'.$page->slug)->assertNotFound();
    }

    public function test_duplicate_creates_a_draft_copy(): void
    {
        $page = $this->makePage(['status' => 1]);

        $this->actingAs($this->admin())
            ->post("/admin/pages/{$page->id}/duplicate")
            ->assertOk()
            ->assertJson(['success' => true]);

        $copy = Page::where('slug', 'like', $page->slug.'-copy%')->first();
        $this->assertNotNull($copy);
        $this->assertSame(0, (int) $copy->status);
        $this->assertFalse($copy->is_system);
    }

    public function test_system_pages_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin())->get('/admin/pages')->assertOk();

        $system = Page::where('system_key', 'shop')->first();
        $this->assertNotNull($system);

        $this->actingAs($this->admin())
            ->delete("/admin/pages/{$system->id}")
            ->assertStatus(422);
    }

    public function test_revisions_are_recorded_and_restorable(): void
    {
        $page = $this->makePage();
        $this->actingAs($this->admin());

        $this->post("/admin/pages/{$page->id}/save-blocks", [
            'blocks' => [['id' => 'a', 'type' => 'heading', 'settings' => ['text' => 'First']]],
        ])->assertOk();

        $this->post("/admin/pages/{$page->id}/save-blocks", [
            'blocks' => [['id' => 'b', 'type' => 'heading', 'settings' => ['text' => 'Second']]],
        ])->assertOk();

        $revisions = $this->get("/admin/pages/{$page->id}/revisions")->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(2, count($revisions));

        $firstRev = \App\Models\PageRevision::where('page_id', $page->id)->orderBy('id')->first();
        $this->post("/admin/pages/{$page->id}/revisions/{$firstRev->id}/restore")->assertOk();

        $this->assertEquals('First', $page->fresh()->blocks[0]['settings']['text']);
    }

    public function test_content_template_pages_edit_and_render(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/pages', [
                'title' => 'About Us Custom',
                'page_type' => 'informational',
                'template' => 'content',
                'status' => 1,
            ])
            ->assertRedirect();

        $page = Page::where('slug', 'about-us-custom')->first();
        $this->assertNotNull($page);

        $this->put("/admin/pages/{$page->id}", [
            'status' => 1,
            'translations' => [
                'en' => ['title' => 'About Us Custom', 'content' => '<p>RichContentMarker7</p>'],
            ],
        ])->assertRedirect(route('admin.pages.index'));

        $this->get("/admin/pages/{$page->id}/preview")->assertOk()->assertSee('RichContentMarker7');
        $this->get('/page/'.$page->slug)->assertOk()->assertSee('RichContentMarker7');
    }

    public function test_system_region_blocks_render_on_shop_page(): void
    {
        $this->actingAs($this->admin());
        $this->get('/admin/pages')->assertOk();

        $shop = Page::where('system_key', 'shop')->first();
        $this->assertNotNull($shop);

        $this->post("/admin/pages/{$shop->id}/save-blocks", [
            'blocks' => [['id' => 'n1', 'type' => 'notice', 'settings' => ['text' => 'RegionNoticeXYZ', 'tone' => 'info']]],
        ])->assertOk();

        $this->get('/products')->assertOk()->assertSee('RegionNoticeXYZ');
    }

    public function test_product_listing_page_accepts_merchandising_blocks(): void
    {
        $this->actingAs($this->admin());

        $shop = Page::where('system_key', 'shop')->first();
        $this->assertNotNull($shop);

        $this->post("/admin/pages/{$shop->id}/save-blocks", [
            'blocks' => [
                ['id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'ListingHeadingXYZ', 'level' => 'h2']],
                ['id' => 'g1', 'type' => 'product_grid', 'settings' => ['title' => 'ListingGridXYZ', 'source' => 'featured', 'limit' => 4, 'columns' => '4']],
            ],
        ])->assertOk();

        $shop->refresh();
        $types = array_column($shop->blocks ?? [], 'type');

        $this->assertContains('heading', $types);
        $this->assertContains('product_grid', $types);

        $this->get('/products')->assertOk()->assertSee('ListingHeadingXYZ');
    }

    public function test_categories_landing_region_renders_blocks(): void
    {
        $this->actingAs($this->admin());

        $registry = app(\App\Services\Pages\PageRegistry::class);
        $registry->ensureSystemPages();

        $page = Page::where('system_key', 'categories')->first();
        $this->assertNotNull($page);

        $this->post("/admin/pages/{$page->id}/save-blocks", [
            'blocks' => [
                ['id' => 'h1', 'type' => 'heading', 'settings' => ['text' => 'CategoriesHeroXYZ', 'level' => 'h1']],
                ['id' => 'ct1', 'type' => 'category_grid', 'settings' => ['title' => 'ShopByDeptXYZ', 'source' => 'top', 'limit' => 6, 'columns' => '4']],
            ],
        ])->assertOk();

        $page->refresh();
        $types = array_column($page->blocks ?? [], 'type');
        $this->assertContains('heading', $types);
        $this->assertContains('category_grid', $types);

        $this->get('/categories')->assertOk()->assertSee('CategoriesHeroXYZ');
    }
}
