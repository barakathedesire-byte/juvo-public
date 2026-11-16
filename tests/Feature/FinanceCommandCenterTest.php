<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorCommission;
use App\Services\Admin\FinanceReportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinanceCommandCenterTest extends TestCase
{
    use DatabaseTransactions;

    protected function staffWithRole(string $roleName): User
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        $user = User::factory()->create(['role' => $roleName]);
        $user->assignRole($role);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }

    protected function vendorAndCommission(string $status = 'available', float $gross = 1000): Vendor
    {
        $vendor = Vendor::create([
            'name' => 'Finco',
            'shop_name' => 'Finco Shop',
            'email' => uniqid('finco').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
            'commission_rate' => 10.0,
        ]);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'status' => 'completed',
            'payment_status' => 'paid',
            'total_amount' => $gross,
            'delivery_fee' => 0,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'coupon_code' => null,
        ]);

        VendorCommission::create([
            'vendor_id' => $vendor->id,
            'order_id' => $order->id,
            'gross_amount' => $gross,
            'commission_rate' => 10.0,
            'commission_amount' => round($gross * 0.10, 2),
            'platform_fee' => 0,
            'net_amount' => round($gross * 0.90, 2),
            'currency' => 'TZS',
            'status' => $status,
        ]);

        return $vendor;
    }

    public function test_finance_center_requires_finance_view_permission(): void
    {
        $denied = $this->staffWithRole('salesperson');
        $this->actingAs($denied)->get(route('admin.finance.index'))->assertForbidden();

        $allowed = $this->staffWithRole('finance_manager');
        $this->actingAs($allowed)->get(route('admin.finance.index'))->assertOk()->assertSee('Finance Command Center');
    }

    public function test_kpis_reflect_commission_data(): void
    {
        $report = app(FinanceReportService::class);

        $before = $report->kpis();

        $this->vendorAndCommission('available', 1000);
        $this->vendorAndCommission('pending', 500);

        $after = $report->kpis();

        $this->assertEqualsWithDelta(1500.0, $after['gross'] - $before['gross'], 0.01);
        $this->assertEqualsWithDelta(150.0, $after['revenue'] - $before['revenue'], 0.01);
        $this->assertEqualsWithDelta(900.0, $after['payable'] - $before['payable'], 0.01);
        $this->assertEqualsWithDelta(450.0, $after['pending_earnings'] - $before['pending_earnings'], 0.01);
    }

    public function test_reconciliation_lists_vendors_with_balances(): void
    {
        $vendor = $this->vendorAndCommission('available', 2000);
        $staff = $this->staffWithRole('finance_manager');

        $this->actingAs($staff)
            ->get(route('admin.finance.reconciliation'))
            ->assertOk()
            ->assertSee($vendor->name);
    }

    public function test_ledger_endpoint_lists_transactions(): void
    {
        $vendor = $this->vendorAndCommission();
        \App\Models\VendorTransaction::create([
            'vendor_id' => $vendor->id,
            'type' => 'adjustment',
            'amount' => 250,
            'currency' => 'TZS',
            'balance_after' => 250,
            'reference' => 'adj-'.uniqid(),
            'description' => 'Manual credit',
        ]);

        $staff = $this->staffWithRole('finance_manager');

        $this->actingAs($staff)
            ->get(route('admin.finance.ledger.data'))
            ->assertOk();
    }
}
