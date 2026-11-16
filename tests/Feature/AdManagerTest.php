<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Ads\AdApprovalService;
use App\Services\Ads\AdDeliveryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdManagerTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::firstOrFail();
    }

    protected function campaign(array $attrs = []): AdCampaign
    {
        $product = Product::where('status', 1)->firstOrFail();

        return AdCampaign::create(array_merge([
            'vendor_id' => $this->vendor()->id,
            'product_id' => $product->id,
            'title' => 'Test campaign',
            'total_budget' => 1000,
            'amount_paid' => 1000,
            'status' => 'submitted',
            'billing_model' => 'cpm',
        ], $attrs));
    }

    public function test_ad_manager_overview_allows_admin_and_blocks_other_roles(): void
    {
        $this->actingAs($this->admin())->get('/admin/ad-manager')->assertOk();

        $viewer = User::factory()->create(['role' => 'viewer']);
        $this->actingAs($viewer)->get('/admin/ad-manager')->assertForbidden();
    }

    public function test_approval_service_lifecycle(): void
    {
        $campaign = $this->campaign();
        $before = \App\Models\AdModerationLog::where('campaign_id', $campaign->id)->count();

        app(AdApprovalService::class)->approve($campaign, 'Looks good.', $this->admin()->id);
        $campaign->refresh();

        $this->assertSame('active', $campaign->status);
        $this->assertGreaterThan($before, \App\Models\AdModerationLog::where('campaign_id', $campaign->id)->count());
    }

    public function test_tracking_records_impression_and_daily_stats(): void
    {
        $campaign = $this->campaign(['status' => 'active', 'spent' => 0]);
        $events = AdEvent::where('campaign_id', $campaign->id)->count();

        app(AdDeliveryService::class)->recordImpression($campaign);
        $campaign->refresh();

        $this->assertSame($events + 1, AdEvent::where('campaign_id', $campaign->id)->count());
        $this->assertSame(1, (int) $campaign->impressions);
    }

    public function test_rejection_notifies_vendor(): void
    {
        $campaign = $this->campaign();
        $vendor = $campaign->vendor;
        $before = $vendor->notifications()->count();

        app(AdApprovalService::class)->reject($campaign, 'Budget too low.', $this->admin()->id);

        $this->assertSame($before + 1, $vendor->notifications()->count());
        $this->assertSame('rejected', $vendor->notifications()->first()->data['event']);
    }

    public function test_invoice_page_renders(): void
    {
        $campaign = $this->campaign();

        $this->actingAs($this->admin())
            ->get("/admin/ad-manager/payments/{$campaign->id}/invoice")
            ->assertOk()
            ->assertSee('Advertising Invoice');
    }

    public function test_creative_version_can_be_added_and_approved(): void
    {
        $campaign = $this->campaign();
        $admin = $this->admin();

        $this->actingAs($admin)->post("/admin/ad-manager/campaigns/{$campaign->id}/creatives", [
            'headline' => 'Version B',
            'body' => 'Body',
            'target_url' => 'https://example.com/deal',
        ])->assertRedirect();

        $creative = $campaign->creatives()->first();
        $this->assertNotNull($creative);
        $this->assertSame(1, $creative->version);

        $this->actingAs($admin)->post("/admin/ad-manager/campaigns/{$campaign->id}/creatives/{$creative->id}/approve")
            ->assertRedirect();

        $this->assertTrue($creative->fresh()->is_approved);
        $this->assertSame('Version B', $campaign->fresh()->creative_headline);
    }
}
