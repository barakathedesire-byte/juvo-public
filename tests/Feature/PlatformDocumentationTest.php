<?php

namespace Tests\Feature;

use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class PlatformDocumentationTest extends TestCase
{
    use GrantsAdminAccess;

    public function test_documentation_page_is_reachable(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.docs.index'))
            ->assertOk()
            ->assertSee('Platform Documentation')
            ->assertSee('Storefront Builder')
            ->assertSee('Regions &amp; Locations', false);
    }

    public function test_search_filters_items(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.docs.index', ['q' => 'storefront']))
            ->assertOk()
            ->assertSee('Choose Global or Vendor-specific scope');
    }

    public function test_category_filter_is_applied(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.docs.index', ['category' => 'Endpoint']))
            ->assertOk()
            ->assertSee('API Endpoints')
            ->assertDontSee('Add a region');
    }

    public function test_endpoints_are_auto_documented(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.docs.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertOk()
            ->assertSee('admin.docs.index');
    }

    public function test_storefront_create_page_is_reachable(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.storefront.create'))
            ->assertOk()
            ->assertSee('New Storefront Template');
    }
}
