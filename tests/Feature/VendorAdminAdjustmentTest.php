<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class VendorAdminAdjustmentTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Adjustee',
            'shop_name' => 'Adjust Shop',
            'email' => uniqid('adj').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_admin_can_credit_a_vendor_wallet(): void
    {
        $vendor = $this->vendor();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 5000,
            'reason' => 'Goodwill credit',
        ])->assertRedirect();

        $txn = \App\Models\VendorTransaction::where('vendor_id', $vendor->id)->where('type', 'adjustment')->firstOrFail();
        $this->assertEqualsWithDelta(5000.0, (float) $txn->amount, 0.01);

        $balances = app(\App\Services\Vendor\VendorFinanceService::class)->balances($vendor->fresh());
        $this->assertEqualsWithDelta(5000.0, $balances['available'], 0.01);
        $this->assertEqualsWithDelta(5000.0, $balances['withdrawable'], 0.01);
    }

    public function test_admin_can_debit_a_vendor(): void
    {
        $vendor = $this->vendor();
        $admin = $this->admin();

        app(\App\Services\Vendor\VendorFinanceService::class)->adjust($vendor, 8000, 'Initial credit');

        $this->actingAs($admin)->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => -3000,
            'reason' => 'Chargeback',
        ])->assertRedirect();

        $balances = app(\App\Services\Vendor\VendorFinanceService::class)->balances($vendor->fresh());
        $this->assertEqualsWithDelta(5000.0, $balances['available'], 0.01);
    }

    public function test_adjustment_requires_a_reason(): void
    {
        $vendor = $this->vendor();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 1000,
        ])->assertSessionHasErrors('reason');
    }

    public function test_admin_vendor_page_shows_stats_and_balance(): void
    {
        $vendor = $this->vendor();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.vendors.show', $vendor->id))
            ->assertOk()
            ->assertSee('Wallet balance');
    }
}
