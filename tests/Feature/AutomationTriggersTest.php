<?php

namespace Tests\Feature;

use App\Models\AdCampaign;
use App\Models\AutomationRun;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Vendor;
use App\Models\Workflow;
use App\Services\Ads\AdApprovalService;
use App\Services\Intelligence\AutomationRegistry;
use App\Services\Intelligence\WorkflowDispatcher;
use App\Services\Store\InventoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutomationTriggersTest extends TestCase
{
    use DatabaseTransactions;

    protected function dispatcher(): WorkflowDispatcher
    {
        return app(WorkflowDispatcher::class);
    }

    protected function workflowId(string $action): int
    {
        return (int) Workflow::where('action', $action)->value('id');
    }

    protected function lastRun(string $action): AutomationRun
    {
        return AutomationRun::where('workflow_id', $this->workflowId($action))
            ->latest('id')
            ->firstOrFail();
    }

    protected function stepResult(AutomationRun $run): array
    {
        return (array) (((array) $run->output)['steps'][0]['result'] ?? []);
    }

    protected function setConfig(string $action, array $config): void
    {
        Workflow::where('action', $action)->firstOrFail()->update(['config' => $config]);
    }

    protected function pendingVendor(array $attributes = []): Vendor
    {
        return Vendor::create(array_merge([
            'name' => 'Pending Vendor',
            'shop_name' => 'Pending Vendor Shop',
            'email' => uniqid('vendor').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'inactive',
            'is_approved' => false,
            'business_documents' => ['vendor_documents/'.uniqid().'.pdf'],
        ], $attributes));
    }

    protected function campaign(array $attributes = []): AdCampaign
    {
        return AdCampaign::create(array_merge([
            'vendor_id' => Vendor::firstOrFail()->id,
            'product_id' => Product::where('status', 1)->firstOrFail()->id,
            'title' => 'Trigger test campaign',
            'total_budget' => 1000,
            'amount_paid' => 0,
            'status' => 'submitted',
            'billing_model' => 'cpm',
        ], $attributes));
    }

    /* ------------------------- Registry ------------------------- */

    public function test_registry_exposes_the_four_legacy_actions(): void
    {
        $keys = collect(app(AutomationRegistry::class)->catalogue())->pluck('key');

        foreach (['vendor_onboarding_approval', 'ad_campaign_approval', 'order_ready_for_pickup', 'low_stock_alert'] as $key) {
            $this->assertContains($key, $keys);
        }
    }

    public function test_dispatch_trigger_is_a_noop_without_a_bound_workflow(): void
    {
        $this->assertSame(0, $this->dispatcher()->dispatchTrigger('no_such_trigger', ['anything' => 1]));
    }

    /* ------------------------- Vendor onboarding ------------------------- */

    public function test_vendor_signup_trigger_runs_and_holds_the_vendor_for_review(): void
    {
        $this->setConfig('vendor_onboarding_approval', ['auto_approve' => false, 'require_documents' => true]);
        $vendor = $this->pendingVendor();

        $this->assertSame(1, $this->dispatcher()->dispatchTrigger('vendor_signup', ['vendor_id' => $vendor->id]));

        $run = $this->lastRun('vendor_onboarding_approval');
        $this->assertSame('succeeded', $run->status);
        $this->assertSame('event', $run->trigger);
        $this->assertSame('awaiting_review', $this->stepResult($run)['outcome']);
        $this->assertFalse((bool) $vendor->fresh()->is_approved);
    }

    public function test_vendor_onboarding_approves_when_documents_are_present_and_allowed(): void
    {
        $this->setConfig('vendor_onboarding_approval', ['auto_approve' => true, 'require_documents' => true]);
        $vendor = $this->pendingVendor();

        $this->dispatcher()->dispatchTrigger('vendor_signup', ['vendor_id' => $vendor->id]);

        $this->assertSame('approved', $this->stepResult($this->lastRun('vendor_onboarding_approval'))['outcome']);

        $vendor->refresh();
        $this->assertTrue((bool) $vendor->is_approved);
        $this->assertTrue($vendor->isApproved());
    }

    public function test_vendor_onboarding_holds_a_vendor_that_uploaded_no_documents(): void
    {
        $this->setConfig('vendor_onboarding_approval', ['auto_approve' => true, 'require_documents' => true]);
        $vendor = $this->pendingVendor(['business_documents' => []]);

        $this->dispatcher()->dispatchTrigger('vendor_signup', ['vendor_id' => $vendor->id]);

        $run = $this->lastRun('vendor_onboarding_approval');
        $this->assertSame('held', $this->stepResult($run)['outcome']);
        $this->assertFalse((bool) $vendor->fresh()->is_approved);
    }

    public function test_vendor_onboarding_reports_a_missing_vendor(): void
    {
        $this->dispatcher()->dispatchTrigger('vendor_signup', ['vendor_id' => 999999]);

        $this->assertFalse((bool) $this->stepResult($this->lastRun('vendor_onboarding_approval'))['ok']);
    }

    /* ------------------------- Ad campaigns ------------------------- */

    public function test_ad_submit_trigger_reaches_the_approval_action(): void
    {
        $campaign = $this->campaign();

        $this->assertSame(1, $this->dispatcher()->dispatchTrigger('ad_submit', [
            'campaign_id' => $campaign->id,
            'vendor_id' => $campaign->vendor_id,
            'product_id' => $campaign->product_id,
        ]));

        $run = $this->lastRun('ad_campaign_approval');
        $this->assertSame('succeeded', $run->status);
        $this->assertSame('held', $this->stepResult($run)['outcome']);
    }

    public function test_ad_approval_action_leaves_a_decided_campaign_alone(): void
    {
        $campaign = $this->campaign(['status' => 'rejected']);

        $this->dispatcher()->dispatchTrigger('ad_submit', ['campaign_id' => $campaign->id]);

        $this->assertSame('already_decided', $this->stepResult($this->lastRun('ad_campaign_approval'))['outcome']);
        $this->assertSame('rejected', $campaign->fresh()->status);
    }

    public function test_submitting_a_campaign_fires_the_ad_submit_trigger(): void
    {
        $workflowId = $this->workflowId('ad_campaign_approval');
        $campaign = $this->campaign(['status' => 'draft']);

        $before = AutomationRun::where('workflow_id', $workflowId)->count();
        app(AdApprovalService::class)->submit($campaign);

        $this->assertSame($before + 1, AutomationRun::where('workflow_id', $workflowId)->count());
    }

    /* ------------------------- Order ready for pickup ------------------------- */

    public function test_order_trigger_skips_when_fleetbase_is_not_configured(): void
    {
        config()->set('fleetbase.api_key', '');

        $order = Order::query()->firstOrFail();
        $order->status = 'ready_for_pickup';
        $order->save();

        $this->dispatcher()->dispatchTrigger('order_status_change', [
            'order_id' => $order->id,
            'from_status' => 'processing',
            'to_status' => 'ready_for_pickup',
        ]);

        $run = $this->lastRun('order_ready_for_pickup');
        $this->assertSame('succeeded', $run->status);
        $this->assertSame('skipped', $this->stepResult($run)['outcome']);
        $this->assertSame('fleetbase_not_configured', $this->stepResult($run)['reason']);
    }

    /* ------------------------- Low stock ------------------------- */

    public function test_stock_update_trigger_flags_low_stock_without_mutating_stock(): void
    {
        $variant = ProductVariant::query()->firstOrFail();

        $variant->stock = 2;
        $variant->low_stock_threshold = 50;
        $variant->save();

        $this->dispatcher()->dispatchTrigger('stock_update', ['variant_id' => $variant->id]);

        $run = $this->lastRun('low_stock_alert');
        $this->assertSame('succeeded', $run->status);

        $result = $this->stepResult($run);
        $this->assertSame('flagged', $result['outcome']);
        $this->assertContains($variant->id, array_column($result['variants'], 'id'));

        $this->assertSame(2, (int) $variant->fresh()->stock);
    }

    public function test_adjusting_stock_fires_the_stock_update_trigger(): void
    {
        $workflowId = $this->workflowId('low_stock_alert');
        $variant = ProductVariant::query()->firstOrFail();

        $before = AutomationRun::where('workflow_id', $workflowId)->count();
        app(InventoryService::class)->adjust($variant, (int) $variant->stock + 1);

        $this->assertSame($before + 1, AutomationRun::where('workflow_id', $workflowId)->count());
    }
}
