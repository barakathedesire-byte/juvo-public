<?php

namespace Tests\Feature;

use App\Models\HomepageSection;
use App\Services\Homepage\HomepageService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class HomepageRowsSectionTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_rows_section_can_be_created_through_the_admin_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.homepage.sections.store'), [
            'type' => 'product_grid',
            'title' => 'Shop by category',
            'layout' => 'rows',
            'status' => 'published',
            'limit' => 10,
            'rows' => [
                ['title' => 'Deals', 'source' => 'daily_deals', 'layout' => 'carousel', 'limit' => 6, 'columns_desktop' => 5, 'columns_tablet' => 3, 'columns_mobile' => 2, 'see_all' => '1'],
                ['title' => 'New in', 'source' => 'new_arrivals', 'layout' => 'grid', 'limit' => 4, 'columns_desktop' => 4, 'columns_tablet' => 2, 'columns_mobile' => 2, 'see_all' => '0'],
            ],
        ])->assertRedirect(route('admin.homepage.sections.index'));

        $section = HomepageSection::where('title', 'Shop by category')->firstOrFail();

        $this->assertSame('rows', $section->layout);
        $this->assertCount(2, $section->settings['rows']);
        $this->assertSame('Deals', $section->settings['rows'][0]['title']);
        $this->assertSame('grid', $section->settings['rows'][1]['layout']);
        $this->assertTrue($section->settings['rows'][0]['see_all']);
        $this->assertFalse($section->settings['rows'][1]['see_all']);
        $this->assertSame(5, $section->settings['rows'][0]['columns_desktop']);
    }

    public function test_rows_layout_skips_the_single_source_requirement(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.homepage.sections.store'), [
            'type' => 'product_grid',
            'title' => 'Rows section',
            'layout' => 'rows',
            'status' => 'published',
            'source' => 'category',
            'rows' => [['title' => 'Row', 'source' => 'featured', 'limit' => 6]],
        ])->assertRedirect(route('admin.homepage.sections.index'));

        $this->assertSame(1, HomepageSection::where('title', 'Rows section')->count());
    }

    public function test_resolver_expands_rows_into_resolved_rows(): void
    {
        $section = HomepageSection::create([
            'type' => 'product_grid',
            'title' => 'Stacked',
            'layout' => 'rows',
            'source' => 'featured',
            'limit' => 10,
            'status' => 'published',
            'sort_order' => 1,
            'settings' => [
                'rows' => [
                    ['title' => 'Row A', 'source' => 'new_arrivals', 'layout' => 'grid', 'limit' => 3, 'columns_desktop' => 4, 'columns_tablet' => 2, 'columns_mobile' => 2, 'see_all' => true],
                    ['title' => 'Row B', 'source' => 'featured', 'layout' => 'carousel', 'limit' => 2, 'columns_desktop' => 5, 'columns_tablet' => 3, 'columns_mobile' => 2, 'see_all' => false],
                ],
            ],
        ]);

        $resolved = app(HomepageService::class)->sections()->firstWhere('id', $section->id);

        $this->assertArrayHasKey('rows', $resolved->resolved);
        $this->assertCount(2, $resolved->resolved['rows']);

        $row = $resolved->resolved['rows'][0];
        $this->assertSame('Row A', $row['title']);
        $this->assertSame('grid', $row['layout']);
        $this->assertSame(4, $row['columns']['desktop']);
        $this->assertTrue($row['see_all']);
        $this->assertArrayHasKey('products', $row);
        $this->assertArrayHasKey('see_all_url', $row);
    }

    public function test_rows_section_is_hidden_when_every_row_is_empty(): void
    {
        HomepageSection::create([
            'type' => 'product_grid',
            'title' => 'Empty rows',
            'layout' => 'rows',
            'source' => 'featured',
            'limit' => 10,
            'status' => 'published',
            'sort_order' => 1,
            'settings' => ['rows' => []],
        ]);

        $this->get('/')->assertOk()->assertDontSee('Empty rows');
    }

    public function test_edit_form_offers_the_rows_layout(): void
    {
        $section = HomepageSection::create([
            'type' => 'product_grid',
            'title' => 'Edit me',
            'layout' => 'rows',
            'source' => 'featured',
            'limit' => 10,
            'status' => 'published',
            'sort_order' => 1,
            'settings' => ['rows' => [['title' => 'R', 'source' => 'featured']]],
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.homepage.sections.edit', $section))
            ->assertOk()
            ->assertSee('Multiple rows')
            ->assertSee('rows[0][title]', false);
    }
}
