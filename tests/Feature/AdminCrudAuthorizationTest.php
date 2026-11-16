<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Access\AccountStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AdminCrudAuthorizationTest extends TestCase
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

    public function test_viewer_cannot_create_a_category(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/categories', [
                'translations' => ['en' => ['name' => 'Evil Category']],
                'status' => 1,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('category_translations', ['name' => 'Evil Category']);
    }

    public function test_viewer_cannot_create_a_brand(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/brands', ['name' => 'Evil Brand'])
            ->assertForbidden();
    }

    public function test_viewer_cannot_delete_a_customer(): void
    {
        $customer = \App\Models\Customer::create([
            'name' => 'C', 'email' => uniqid('c').'@example.com', 'password' => bcrypt('x'), 'status' => 'active',
        ]);

        $this->actingAs($this->viewer())
            ->delete('/admin/customers/'.$customer->id)
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_an_attribute(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/attributes', ['name' => 'Evil Attribute', 'type' => 'text'])
            ->assertForbidden();
    }

    public function test_viewer_cannot_delete_an_order(): void
    {
        $this->actingAs($this->viewer())
            ->delete('/admin/orders/1')
            ->assertForbidden();
    }

    public function test_viewer_cannot_create_a_currency(): void
    {
        $this->actingAs($this->viewer())
            ->post('/admin/currencies', ['code' => 'XXX', 'name' => 'Evil', 'symbol' => 'X', 'rate' => 1])
            ->assertForbidden();
    }
}
