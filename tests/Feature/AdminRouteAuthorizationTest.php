<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Access\AccountStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminRouteAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected function viewer(): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'dashboard.view', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'role' => 'viewer',
            'account_status' => AccountStatus::ACTIVE,
        ]);
        $user->givePermissionTo(['dashboard.view']);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_viewer_cannot_create_a_region(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/regions', ['name' => 'Injected Region'])
            ->assertForbidden();
    }

    public function test_viewer_cannot_delete_a_region(): void
    {
        $region = \App\Models\Region::create(['name' => 'Somewhere']);

        $this->actingAs($this->viewer())
            ->delete('/admin/regions/'.$region->id)
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_a_storefront_template(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/storefront', ['name' => 'Evil Template', 'scope' => 'global'])
            ->assertForbidden();
    }

    public function test_viewer_cannot_publish_a_storefront_template(): void
    {
        $template = \App\Models\StorefrontTemplate::create([
            'name' => 'Tpl',
            'scope' => 'global',
            'blocks' => [],
            'status' => 0,
        ]);

        $this->actingAs($this->viewer())
            ->post('/admin/storefront/'.$template->id.'/publish')
            ->assertForbidden();
    }

    public function test_viewer_cannot_update_a_product_report(): void
    {
        $vendor = \App\Models\Vendor::create([
            'name' => 'V', 'shop_name' => 'V Shop', 'email' => uniqid('v').'@example.com',
            'password' => \Illuminate\Support\Facades\Hash::make('x'), 'status' => 'active', 'is_approved' => true,
        ]);
        $shop = \App\Models\Shop::create(['vendor_id' => $vendor->id, 'name' => 'S', 'slug' => 's-'.uniqid(), 'status' => 'active']);
        $product = \App\Models\Product::create([
            'shop_id' => $shop->id, 'vendor_id' => $vendor->id, 'slug' => 'p-'.uniqid(),
            'category_id' => \App\Models\Category::first()->id, 'brand_id' => null,
            'product_type' => 'simple', 'status' => 1, 'approval_status' => 'approved', 'visibility' => 'public',
        ]);
        $report = \App\Models\ProductReport::create([
            'product_id' => $product->id, 'issue_type' => 'spam', 'status' => 'pending',
        ]);

        $this->actingAs($this->viewer())
            ->put('/admin/product-reports/'.$report->id, ['status' => 'resolved'])
            ->assertForbidden();

        $this->assertSame('pending', $report->fresh()->status);
    }
}
