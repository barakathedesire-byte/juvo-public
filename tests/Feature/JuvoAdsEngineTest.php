<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdEvent;
use App\Models\AdPlacement;
use App\Models\AdPolicyReview;
use App\Models\AdTargetingRule;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Ads\AdAttributionService;
use App\Services\Ads\AdAuctionService;
use App\Services\Ads\AdAuditService;
use App\Services\Ads\AdDeliveryService;
use App\Services\Ads\AdLedgerService;
use App\Services\Ads\AdOptimizationService;
use App\Services\Ads\AdPolicyService;
use App\Support\Ads\Money;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class JuvoAdsEngineTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function productFor(Vendor $vendor): Product
    {
        return Product::where('vendor_id', $vendor->id)->where('status', 1)->firstOrFail();
    }

    protected function placement(string $key = 'test_slot'): AdPlacement
    {
        return AdPlacement::firstOrCreate(
            ['key' => $key],
            ['name' => 'Test Slot', 'pricing_model' => 'cpc', 'is_active' => true, 'max_active' => 0]
        );
    }

    protected function engineCampaign(array $attrs = []): AdCampaign
    {
        $vendor = $this->vendor();

        return AdCampaign::create(array_merge([
            'vendor_id' => $vendor->id,
            'product_id' => $this->productFor($vendor)->id,
            'title' => 'Engine campaign',
            'placement_id' => $this->placement()->id,
            'billing_model' => 'cpc',
            'currency' => 'TZS',
            'bid_type' => 'manual_cpc',
            'bid_minor' => 300,
            'quality_factor' => 1.0,
            'total_budget_minor' => 100000,
            'amount_paid_minor' => 100000,
            'spent_minor' => 0,
            'reserved_minor' => 0,
            'total_budget' => 1000,
            'amount_paid' => 1000,
            'spent' => 0,
            'status' => 'active',
            'start_date' => today()->subDay(),
        ], $attrs));
    }

    protected function fundedCampaign(array $attrs = [], ?int $fundMinor = null): AdCampaign
    {
        $campaign = $this->engineCampaign($attrs);
        app(AdLedgerService::class)->fundCampaign(
            $campaign,
            $fundMinor ?? (int) $campaign->amount_paid_minor
        );

        return $campaign->fresh();
    }

    protected function request(string $ua = 'Mozilla/5.0', string $ip = '203.0.113.10'): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => $ip,
            'HTTP_USER_AGENT' => $ua,
        ]);
    }

    /* ----------------------------- Money ----------------------------- */

    public function test_money_parses_decimals_to_minor_units_exactly(): void
    {
        $this->assertSame(1000, Money::minor('10.00'));
        $this->assertSame(1001, Money::minor('10.005'));
        $this->assertSame(999, Money::minor('9.99'));
        $this->assertSame(0, Money::minor(null));
        $this->assertSame('10.01', Money::decimal(1001));
    }

    /* ----------------------------- Ledger ----------------------------- */

    public function test_ledger_funds_charges_and_is_idempotent(): void
    {
        $vendor = $this->vendor();
        $campaign = $this->engineCampaign();
        $ledger = app(AdLedgerService::class);

        $account = $ledger->accountFor($vendor);
        $ledger->fund($account, 10000, 'test:fund:1');
        $this->assertSame(10000, $account->fresh()->funded_minor);

        // Idempotent: same reference never double-credits.
        $ledger->fund($account->fresh(), 10000, 'test:fund:1');
        $this->assertSame(10000, $account->fresh()->funded_minor);

        $this->assertNotNull($ledger->charge($campaign, 3000, 'test:spend:1'));
        $this->assertSame(3000, $account->fresh()->spent_minor);
        $this->assertSame(7000, $account->fresh()->available_minor);

        // Retry of the same spend is a no-op.
        $ledger->charge($campaign, 3000, 'test:spend:1');
        $this->assertSame(3000, $account->fresh()->spent_minor);

        // Cannot overspend.
        $this->assertNull($ledger->charge($campaign, 8000, 'test:spend:2'));
        $this->assertSame(3000, $account->fresh()->spent_minor);
    }

    public function test_reservations_hold_budget_and_commit_to_spend(): void
    {
        $campaign = $this->engineCampaign(['amount_paid_minor' => 5000, 'bid_minor' => 4000]);
        $ledger = app(AdLedgerService::class);
        $account = $ledger->accountFor($campaign->vendor_id);
        $ledger->fund($account, 5000, 'res:fund');

        $reservation = $ledger->reserve($campaign, 4000, 'lifetime', 'lifetime', 'res:1');
        $this->assertNotNull($reservation);
        $this->assertSame(4000, $account->fresh()->reserved_minor);
        $this->assertSame(1000, $account->fresh()->available_minor);

        // Second reservation cannot exceed the available balance.
        $this->assertNull($ledger->reserve($campaign, 2000, 'lifetime', 'lifetime', 'res:2'));

        $entry = $ledger->charge($campaign->fresh(), 4000, 'res:spend', [], $reservation);
        $this->assertNotNull($entry);
        $this->assertSame(0, $account->fresh()->reserved_minor);
        $this->assertSame(4000, $account->fresh()->spent_minor);

        $drift = $ledger->reconcile($account->fresh());
        $this->assertSame(['funded_minor' => 0, 'spent_minor' => 0, 'reserved_minor' => 0], [
            'funded_minor' => $drift['funded'],
            'spent_minor' => $drift['spent'],
            'reserved_minor' => $drift['reserved'],
        ]);
    }

    /* ----------------------------- Auction ----------------------------- */

    public function test_auction_ranks_by_expected_revenue_and_records_decision(): void
    {
        $placement = $this->placement('auc_slot');
        $low = $this->engineCampaign(['placement_id' => $placement->id, 'bid_minor' => 200, 'title' => 'Low']);
        $high = $this->engineCampaign(['placement_id' => $placement->id, 'bid_minor' => 800, 'title' => 'High']);

        $winners = app(AdAuctionService::class)->serve('auc_slot', [], 1);

        $this->assertCount(1, $winners);
        $this->assertSame($high->id, $winners->first()->id);

        $decision = \App\Models\AdAuctionDecision::where('placement_key', 'auc_slot')->latest('id')->first();
        $this->assertNotNull($decision);
        $this->assertSame(2, $decision->candidate_count);
        $this->assertContains($low->id, collect($decision->candidates)->pluck('campaign_id')->all());
    }

    public function test_auction_excludes_exhausted_budget_with_reason(): void
    {
        $placement = $this->placement('auc_exhaust');
        $campaign = $this->engineCampaign([
            'placement_id' => $placement->id,
            'amount_paid_minor' => 1000,
            'spent_minor' => 1000,
        ]);

        $candidates = app(AdAuctionService::class)->candidates($placement, []);
        $row = $candidates->firstWhere('campaign_id', $campaign->id);

        $this->assertFalse($candidates->firstWhere('campaign_id', $campaign->id)['eligible']);
        $this->assertContains('budget_exhausted', $row['reasons']);
    }

    public function test_auction_honours_exclude_targeting_rules(): void
    {
        $placement = $this->placement('auc_target');
        $vendor = $this->vendor();
        $campaign = $this->engineCampaign(['placement_id' => $placement->id]);

        AdTargetingRule::create([
            'campaign_id' => $campaign->id,
            'kind' => 'exclude',
            'dimension' => 'vendor',
            'operator' => 'in',
            'value' => (string) $vendor->id,
            'created_at' => now(),
        ]);

        $winners = app(AdAuctionService::class)->serve('auc_target', ['vendor_id' => $vendor->id], 1);
        $this->assertCount(0, $winners);

        $candidates = app(AdAuctionService::class)->candidates($placement, ['vendor_id' => $vendor->id]);
        $row = $candidates->firstWhere('campaign_id', $campaign->id);
        $this->assertFalse($row['eligible']);
        $this->assertContains('targeting_exclude_vendor', $row['reasons']);
    }

    public function test_auction_is_deterministic_for_identical_inputs(): void
    {
        $placement = $this->placement('auc_deterministic');
        $this->engineCampaign(['placement_id' => $placement->id, 'bid_minor' => 300, 'title' => 'A']);
        $this->engineCampaign(['placement_id' => $placement->id, 'bid_minor' => 300, 'title' => 'B']);

        $auction = app(AdAuctionService::class);
        $first = $auction->serve('auc_deterministic', [], 2, false)->pluck('id')->all();
        $second = $auction->serve('auc_deterministic', [], 2, false)->pluck('id')->all();

        $this->assertSame($first, $second);
    }

    /* ----------------------------- Delivery ----------------------------- */

    public function test_delivery_dedupes_events_by_uid(): void
    {
        $campaign = $this->engineCampaign();
        $before = AdEvent::where('campaign_id', $campaign->id)->count();

        $delivery = app(AdDeliveryService::class);
        $delivery->recordImpression($campaign, $this->request(), ['event_uid' => 'uid-1']);
        $delivery->recordImpression($campaign, $this->request(), ['event_uid' => 'uid-1']);

        $this->assertSame($before + 1, AdEvent::where('campaign_id', $campaign->id)->count());
    }

    public function test_bot_traffic_is_filtered_and_never_billed(): void
    {
        $campaign = $this->engineCampaign(['billing_model' => 'cpc', 'bid_minor' => 300]);
        app(AdLedgerService::class)->fund($campaign, 10000, 'bot:fund');

        $event = app(AdDeliveryService::class)
            ->recordClick($campaign, $this->request('Googlebot/2.1 (+http://www.google.com/bot.html)'));

        $this->assertNotNull($event);
        $this->assertFalse($event->is_valid);
        $this->assertSame('suspected_bot', $event->invalid_reason);
        $this->assertFalse($event->is_billable);
        $this->assertSame(0, (int) $campaign->fresh()->clicks);
    }

    public function test_cpc_click_bills_ledger_and_never_overspends(): void
    {
        $campaign = $this->engineCampaign(['bid_minor' => 300, 'amount_paid_minor' => 500]);
        app(AdLedgerService::class)->fund($campaign, 500, 'cpc:fund');

        $delivery = app(AdDeliveryService::class);
        $delivery->recordClick($campaign->fresh(), $this->request('Mozilla/5.0', '10.0.0.1'), ['event_uid' => 'c1']);
        $delivery->recordClick($campaign->fresh(), $this->request('Mozilla/5.0', '10.0.0.2'), ['event_uid' => 'c2']);

        $account = AdAccount::where('vendor_id', $campaign->vendor_id)->first();
        $this->assertSame(500, $account->spent_minor);
        $this->assertSame(0, $campaign->fresh()->budgetRemainingMinor());

        // Next click has no remaining budget: billable cost is zero.
        $event = $delivery->recordClick($campaign->fresh(), $this->request('Mozilla/5.0', '10.0.0.3'), ['event_uid' => 'c3']);
        $this->assertFalse($event->is_billable);
        $this->assertSame(0, $event->spend_minor);
        $this->assertSame(500, $campaign->fresh()->spent_minor);
    }

    public function test_cpm_bills_on_the_thousandth_impression(): void
    {
        $campaign = $this->engineCampaign([
            'billing_model' => 'cpm',
            'bid_minor' => 8000,
            'amount_paid_minor' => 100000,
        ]);
        $campaign->forceFill(['impressions' => 999])->save();
        app(AdLedgerService::class)->fund($campaign, 100000, 'cpm:fund');

        $event = app(AdDeliveryService::class)->recordImpression($campaign->fresh(), $this->request());

        $this->assertSame(1000, (int) $campaign->fresh()->impressions);
        $this->assertTrue($event->is_billable);
        $this->assertSame(8000, (int) $event->spend_minor);
        $this->assertSame(8000, (int) $campaign->fresh()->spent_minor);
    }

    /* ----------------------------- Attribution ----------------------------- */

    public function test_attribution_is_idempotent_and_counts_real_order_revenue(): void
    {
        $vendor = $this->vendor();
        $product = $this->productFor($vendor);
        $campaign = $this->engineCampaign(['product_id' => $product->id]);

        $attribution = app(AdAttributionService::class);

        \App\Models\AdAttributionTouchpoint::where('product_id', $product->id)
            ->where('event_type', 'click')
            ->where('status', 'pending')
            ->delete();

        $attribution->recordClick($campaign, ['click_id' => 'click-1']);

        $order = Order::create([
            'vendor_id' => $vendor->id,
            'total_amount' => 100,
            'subtotal' => 100,
            'status' => 'pending',
            'payment_status' => 'paid',
            'currency' => 'TZS',
        ]);
        OrderDetail::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'price' => 50,
        ]);

        $first = $attribution->attributeOrder($order);
        $second = $attribution->attributeOrder($order);

        $this->assertCount(1, $first);
        $this->assertCount(0, $second);
        $this->assertSame(10000, $attribution->attributedRevenueMinor($campaign));
        $this->assertSame(1, \App\Models\AdAttributionTouchpoint::where('campaign_id', $campaign->id)->where('status', 'attributed')->count());
    }

    /* ----------------------------- Optimisation ----------------------------- */

    public function test_optimisation_adjusts_bid_within_maximum(): void
    {
        $campaign = $this->engineCampaign([
            'auto_bid_enabled' => true,
            'bid_type' => 'auto_cpc',
            'bid_minor' => 1000,
            'max_cpc_minor' => 1200,
            'amount_paid_minor' => 100000,
        ]);

        \App\Models\AdDailyStat::create([
            'campaign_id' => $campaign->id,
            'date' => today(),
            'impressions' => 1000,
            'clicks' => 60,
            'conversions' => 5,
            'spend' => 0,
            'spend_minor' => 0,
            'currency' => 'TZS',
        ]);

        $run = app(AdOptimizationService::class)->optimize($campaign);

        $this->assertSame('applied', $run->status);
        $this->assertLessThanOrEqual(1200, $run->after_bid_minor);
        $this->assertGreaterThan($run->before_bid_minor, $run->after_bid_minor);
        $this->assertSame($run->after_bid_minor, (int) $campaign->fresh()->bid_minor);
    }

    public function test_optimisation_is_skipped_when_auto_bid_disabled(): void
    {
        $campaign = $this->engineCampaign(['auto_bid_enabled' => false, 'bid_type' => 'manual_cpc']);

        $run = app(AdOptimizationService::class)->optimize($campaign);

        $this->assertSame('skipped', $run->status);
        $this->assertSame('auto_bid_disabled', $run->reason);
    }

    /* ----------------------------- Policy ----------------------------- */

    public function test_policy_scan_flags_prohibited_content_and_records_review(): void
    {
        $campaign = $this->engineCampaign(['creative_body' => 'Guaranteed cure for everything, no risk!']);

        $result = app(AdPolicyService::class)->screen($campaign);

        $this->assertFalse($result['ok']);
        $this->assertSame('prohibited_content', $result['reason_code']);
        $this->assertSame(1, AdPolicyReview::where('campaign_id', $campaign->id)->count());
    }

    public function test_policy_review_is_audited(): void
    {
        $campaign = $this->engineCampaign();

        app(AdPolicyService::class)->review($campaign, 'approved', 'approved', 'Looks fine');

        $this->assertDatabaseHas('ad_policy_reviews', ['campaign_id' => $campaign->id, 'decision' => 'approved']);
        $this->assertDatabaseHas('ad_audit_logs', ['action' => 'policy_review', 'campaign_id' => $campaign->id]);
    }

    /* ----------------------------- End-to-end ----------------------------- */

    public function test_vendor_submission_initialises_engine_fields_and_verification_funds_account(): void
    {
        $vendor = $this->vendor();
        $vendor->forceFill(['email_verified_at' => now()])->save();
        $product = $this->productFor($vendor);

        $this->actingAs($vendor, 'vendor')->post(route('vendor.ads.store'), [
            'product_id' => $product->id,
            'title' => 'Juvo engine campaign',
            'total_budget' => 20000,
            'days' => 10,
            'payment_method' => 'mobilemoney',
            'billing_model' => 'cpm',
            'placement_id' => $this->placement()->id,
        ])->assertRedirect(route('vendor.ads.index'));

        $campaign = AdCampaign::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();
        $this->assertSame(2000000, (int) $campaign->total_budget_minor);
        $this->assertSame(0, (int) $campaign->amount_paid_minor);

        $this->actingAs($this->admin())
            ->post(route('admin.ad-manager.payments.verify', $campaign->id))
            ->assertRedirect();

        $campaign->refresh();
        $account = AdAccount::where('vendor_id', $vendor->id)->first();

        $this->assertSame(2000000, (int) $campaign->amount_paid_minor);
        $this->assertNotNull($account);
        $this->assertSame(2000000, (int) $account->funded_minor);
    }

    /* ----------------------------- Admin UI ----------------------------- */

    public function test_admin_engine_pages_render_for_admin_and_block_other_roles(): void
    {
        $admin = $this->admin();
        $viewer = User::factory()->create(['role' => 'viewer']);

        foreach (['finances', 'auctions', 'policies', 'optimization'] as $page) {
            $this->actingAs($admin)->get("/admin/ad-manager/{$page}")->assertOk();
            $this->actingAs($viewer)->get("/admin/ad-manager/{$page}")->assertForbidden();
        }
    }

    public function test_admin_can_credit_account_and_see_ledger(): void
    {
        $vendor = $this->vendor();
        $account = app(AdLedgerService::class)->accountFor($vendor);

        $this->actingAs($this->admin())
            ->post(route('admin.ad-manager.accounts.credit', $account->id), [
                'amount' => 50,
                'reason' => 'goodwill',
            ])
            ->assertRedirect();

        $this->assertSame(5000, (int) $account->fresh()->funded_minor);
        $this->assertDatabaseHas('ad_audit_logs', ['action' => 'account_credited', 'account_id' => $account->id]);
    }

    public function test_audit_service_records_actor_and_reason(): void
    {
        $campaign = $this->engineCampaign();

        $this->actingAs($this->admin());
        app(AdAuditService::class)->logCampaign('manual_adjust', $campaign, ['bid' => 1], ['bid' => 2], 'test reason');

        $this->assertDatabaseHas('ad_audit_logs', [
            'action' => 'manual_adjust',
            'campaign_id' => $campaign->id,
            'reason' => 'test reason',
        ]);
    }

    /* ----------------------------- Storefront ----------------------------- */

    public function test_storefront_renders_sponsored_slot_for_active_campaign(): void
    {
        $placement = AdPlacement::firstOrCreate(
            ['key' => 'homepage_strip'],
            ['name' => 'Homepage strip', 'pricing_model' => 'cpm', 'is_active' => true]
        );

        $this->engineCampaign([
            'placement_id' => $placement->id,
            'bid_minor' => 50000,
            'creative_headline' => 'Sponsored Spotlight',
        ]);

        $this->get(route('xylo.home'))
            ->assertOk()
            ->assertSee('Sponsored')
            ->assertSee('rel="noopener sponsored"', false);
    }

    /* ----------------------------- RBAC integration ----------------------------- */

    public function test_ad_manager_role_can_reach_engine_admin_pages(): void
    {
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        $role = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'ad_manager', 'guard_name' => 'web']);

        $user = User::factory()->create([
            'role' => 'admin',
            'account_status' => \App\Support\Access\AccountStatus::ACTIVE,
        ]);
        $user->assignRole($role);

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['finances', 'auctions', 'policies', 'optimization'] as $page) {
            $this->actingAs($user->fresh())->get("/admin/ad-manager/{$page}")->assertOk();
        }
    }
}
