<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorPayoutMethod;
use App\Services\Ads\AdApprovalService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdIntegrityTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function enableAdAutoApprove(): void
    {
        app(\App\Services\Rules\RuleEngine::class)->setValue('ad_auto_approve', '1', null, 'test setup');
    }

    public function test_vendor_submission_does_not_self_declare_payment(): void
    {
        $vendor = $this->vendor();
        $product = Product::where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.ads.store'), [
            'product_id' => $product->id,
            'title' => 'Integrity campaign',
            'total_budget' => 20000,
            'days' => 10,
            'payment_method' => 'mobilemoney',
            'billing_model' => 'cpm',
        ])->assertRedirect(route('vendor.ads.index'));

        $campaign = AdCampaign::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();

        $this->assertSame(0.0, (float) $campaign->amount_paid);
        $this->assertNull($campaign->paid_at);
        $this->assertSame('awaiting_payment', $campaign->status);
    }

    public function test_auto_approve_is_blocked_until_paid(): void
    {
        $this->enableAdAutoApprove();

        $product = Product::where('status', 1)->firstOrFail();

        $campaign = AdCampaign::create([
            'vendor_id' => $this->vendor()->id,
            'product_id' => $product->id,
            'title' => 'Unpaid',
            'total_budget' => 20000,
            'amount_paid' => 0,
            'paid_at' => null,
            'status' => 'submitted',
            'billing_model' => 'cpm',
        ]);

        $approved = app(AdApprovalService::class)->autoApprove($campaign);

        $this->assertFalse($approved);
        $this->assertSame('submitted', $campaign->fresh()->status);
    }

    public function test_admin_verification_confirms_amount_and_activates(): void
    {
        $this->enableAdAutoApprove();

        $vendor = $this->vendor();
        $vendor->forceFill(['email_verified_at' => now()])->save();

        $product = Product::where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();

        $campaign = AdCampaign::create([
            'vendor_id' => $vendor->id,
            'product_id' => $product->id,
            'title' => 'Awaiting',
            'total_budget' => 20000,
            'amount_paid' => 0,
            'paid_at' => null,
            'status' => 'awaiting_payment',
            'billing_model' => 'cpm',
            'start_date' => today(),
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.ad-manager.payments.verify', $campaign->id))
            ->assertRedirect();

        $campaign->refresh();
        $this->assertSame(20000.0, (float) $campaign->amount_paid);
        $this->assertNotNull($campaign->paid_at);
        $this->assertNotSame('awaiting_payment', $campaign->status);
        $this->assertContains($campaign->status, ['approved', 'active', 'scheduled']);
    }

    public function test_payout_account_number_is_encrypted_at_rest(): void
    {
        $vendor = $this->vendor();

        $method = VendorPayoutMethod::create([
            'vendor_id' => $vendor->id,
            'method' => 'mobile_money',
            'account_name' => 'Test Vendor',
            'account_number' => '255700000000',
        ]);

        $this->assertSame('255700000000', $method->fresh()->account_number);

        $raw = DB::table('vendor_payout_methods')->where('id', $method->id)->value('account_number');
        $this->assertNotSame('255700000000', $raw);
        $this->assertSame('255700000000', Crypt::decryptString($raw));
    }

    public function test_ad_conversion_requires_authentication(): void
    {
        $product = Product::where('status', 1)->firstOrFail();
        $campaign = AdCampaign::create([
            'vendor_id' => $this->vendor()->id,
            'product_id' => $product->id,
            'title' => 'Conv',
            'total_budget' => 20000,
            'amount_paid' => 20000,
            'paid_at' => now(),
            'status' => 'active',
            'billing_model' => 'cpc',
        ]);

        $before = AdEvent::where('campaign_id', $campaign->id)->count();

        $this->post(route('ads.conversion', $campaign->id))->assertStatus(302);

        $this->assertSame($before, AdEvent::where('campaign_id', $campaign->id)->count());
    }
}
