<?php

namespace Tests\Feature;

use App\Models\ApprovalRequest;
use App\Models\Vendor;
use App\Models\VendorPayout;
use App\Models\VendorTransaction;
use App\Services\Admin\ApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class FinanceApprovalGatingTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::create([
            'name' => 'Gate Vendor',
            'shop_name' => 'Gate Shop',
            'email' => uniqid('gate').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_small_adjustment_posts_immediately(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($this->admin())->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 100,
            'reason' => 'Small goodwill',
        ])->assertRedirect();

        $this->assertDatabaseHas('vendor_transactions', [
            'vendor_id' => $vendor->id,
            'type' => 'adjustment',
        ]);
        $this->assertSame(0, ApprovalRequest::where('ability', 'finance.adjustment.create')->count());
    }

    public function test_large_adjustment_requires_approval_and_does_not_post(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($this->admin())->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 60000,
            'reason' => 'Big adjustment',
        ])->assertRedirect();

        $this->assertDatabaseHas('approval_requests', [
            'ability' => 'finance.adjustment.create',
            'status' => 'pending',
            'amount' => 60000.00,
        ]);
        $this->assertSame(0, VendorTransaction::where('vendor_id', $vendor->id)->count());
    }

    public function test_approved_large_adjustment_then_posts_on_retry(): void
    {
        $vendor = $this->vendor();
        $requester = $this->admin();
        $approver = $this->admin();

        $this->actingAs($requester)->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 60000,
            'reason' => 'Big adjustment',
        ]);

        $request = ApprovalRequest::where('ability', 'finance.adjustment.create')->firstOrFail();
        app(ApprovalService::class)->approve($approver, $request, 'Approved');
        $this->assertSame('approved', $request->fresh()->status);

        $this->actingAs($requester)->post(route('admin.vendors.adjust', $vendor->id), [
            'amount' => 60000,
            'reason' => 'Big adjustment',
        ])->assertRedirect();

        $this->assertDatabaseHas('vendor_transactions', [
            'vendor_id' => $vendor->id,
            'type' => 'adjustment',
            'amount' => 60000.00,
        ]);
        $this->assertSame('executed', $request->fresh()->status);
    }

    public function test_large_payout_release_requires_approval_before_paying(): void
    {
        $vendor = $this->vendor();
        $payout = VendorPayout::create([
            'vendor_id' => $vendor->id,
            'reference' => 'PAY-'.strtoupper(uniqid()),
            'amount' => 250000,
            'currency' => 'TZS',
            'method' => 'manual',
            'status' => 'requested',
            'requested_at' => now(),
        ]);

        $this->actingAs($this->admin())->post(route('admin.payouts.pay', $payout), [])->assertRedirect();

        $this->assertSame('requested', $payout->fresh()->status);
        $this->assertDatabaseHas('approval_requests', [
            'ability' => 'finance.payout.release',
            'status' => 'pending',
        ]);

        $request = ApprovalRequest::where('ability', 'finance.payout.release')->firstOrFail();
        app(ApprovalService::class)->approve($this->admin(), $request, 'Release approved');

        $this->actingAs($this->admin())->post(route('admin.payouts.pay', $payout), [])->assertRedirect();

        $this->assertSame('paid', $payout->fresh()->status);
        $this->assertSame('executed', $request->fresh()->status);
    }
}
