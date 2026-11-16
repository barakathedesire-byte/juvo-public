<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminSidebarVisibilityTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function userWith(array $permissions): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $user = User::factory()->create(['account_status' => 'active', 'portal_access' => true]);
        $user->givePermissionTo($permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function sidebar(User $user): string
    {
        return $this->actingAs($user)->get(route('admin.dashboard'))->assertOk()->getContent();
    }

    protected function order(): Order
    {
        $vendor = Vendor::where('status', 'active')->where('is_approved', 1)->first()
            ?? Vendor::factory()->create(['status' => 'active', 'is_approved' => 1]);

        return Order::create([
            'vendor_id' => $vendor->id,
            'guest_email' => 'viewonly@example.com',
            'total_amount' => 15000,
            'subtotal' => 15000,
            'delivery_fee' => 0,
            'payment_status' => 'pending',
            'currency' => 'TZS',
            'status' => 'pending',
        ]);
    }

    public function test_dashboard_only_user_sees_no_catalog_tab(): void
    {
        $html = $this->sidebar($this->userWith(['dashboard.view']));

        $this->assertStringNotContainsString('id="productMenu"', $html);
        $this->assertStringNotContainsString(route('admin.orders.index'), $html);
        $this->assertStringNotContainsString('>Catalog<', $html);
    }

    public function test_view_only_products_user_sees_the_tab_without_create_controls(): void
    {
        $html = $this->sidebar($this->userWith(['products.view']));

        $this->assertStringContainsString('id="productMenu"', $html);
        $this->assertStringNotContainsString(route('admin.products.create'), $html);
    }

    public function test_products_writer_sees_create_controls(): void
    {
        $html = $this->sidebar($this->userWith(['products.view', 'products.create']));

        $this->assertStringContainsString('id="productMenu"', $html);
        $this->assertStringContainsString(route('admin.products.create'), $html);
    }

    public function test_super_admin_sees_catalog_and_operations_tabs(): void
    {
        $html = $this->sidebar($this->admin());

        $this->assertStringContainsString('id="productMenu"', $html);
        $this->assertStringContainsString(route('admin.orders.index'), $html);
        $this->assertStringContainsString(route('admin.products.create'), $html);
    }

    public function test_view_only_orders_user_gets_no_order_action_controls(): void
    {
        $this->order();

        $response = $this->actingAs($this->userWith(['orders.view']))
            ->postJson(route('admin.orders.data'))
            ->assertOk();

        $this->assertSame('', $response->json('data.0.action'));
    }

    public function test_order_writer_gets_action_controls(): void
    {
        $this->order();

        $response = $this->actingAs($this->userWith(['orders.view', 'orders.edit']))
            ->postJson(route('admin.orders.data'))
            ->assertOk();

        $action = $response->json('data.0.action');

        $this->assertStringContainsString('deleteOrder', $action);
        $this->assertStringContainsString('markReadyForPickup', $action);
    }

    public function test_view_only_orders_user_cannot_delete(): void
    {
        $order = $this->order();

        $this->actingAs($this->userWith(['orders.view']))
            ->delete(route('admin.orders.destroy', $order->id))
            ->assertForbidden();

        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }
}
