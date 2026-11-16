<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminSettingsTabTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    public function test_admin_settings_index_renders(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.admin-settings.index'));

        $response->assertOk();
    }

    public function test_admin_settings_appearance_update_and_cache(): void
    {
        $user = $this->admin();
        $response = $this->actingAs($user)->put(route('admin.admin-settings.appearance.update'), [
            'primary_color' => '#ff6600',
            'accent_color' => '#cc5200',
            'sidebar_bg' => '#182030',
            'sidebar_bg_end' => '#242b3d',
            'sidebar_text' => '#d8e1f0',
            'content_bg' => '#f7f8fc',
            'border_radius' => '18px',
            'font_family' => 'Nunito',
            'brand_name' => 'Juvo Ops',
            'animations' => true,
            'compact_sidebar' => false,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('admin_settings', [
            'key' => 'primary_color',
            'group' => 'appearance',
        ]);
    }

    public function test_admin_settings_creates_custom_tab(): void
    {
        $user = $this->admin();
        $response = $this->actingAs($user)->post(route('admin.admin-settings.tabs.store'), [
            'label' => 'Fulfilment',
            'url' => '/admin/orders',
            'icon' => 'fas fa-truck',
            'group' => 'Operations',
            'permission' => 'orders.view',
            'sort' => 10,
            'is_enabled' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('admin_portal_tabs', [
            'label' => 'Fulfilment',
            'url' => '/admin/orders',
            'is_custom' => true,
        ]);
    }
}
