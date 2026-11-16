<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdModerationLog;
use App\Models\BusinessRule;
use App\Services\Intelligence\WorkflowDispatcher;
use Illuminate\Support\Facades\Auth;

class AdApprovalService
{
    /**
     * Evaluate a campaign against the platform business rules.
     * Returns [passed => bool, reasons => string[]].
     */
    public function evaluate(AdCampaign $campaign): array
    {
        $reasons = [];

        if (! BusinessRule::truthy('ad_enabled', true)) {
            $reasons[] = 'Advertising is currently disabled platform-wide.';
        }

        $minBudget = \App\Support\Ads\AdBudget::minimum();
        if ($minBudget > 0 && $campaign->amount_paid < $minBudget) {
            $reasons[] = 'Paid budget is below the minimum of '.number_format($minBudget).'.';
        }

        $product = $campaign->product;
        if (! $product) {
            $reasons[] = 'The product no longer exists.';
        } elseif (! in_array((string) $product->status, ['1', 'active'], true)) {
            $reasons[] = 'The product is not active.';
        }

        $vendor = $campaign->vendor;
        if (! $vendor) {
            $reasons[] = 'Vendor account missing.';
        } else {
            if (! $vendor->isApproved()) {
                $reasons[] = 'Vendor is not approved.';
            }
            if (! $vendor->email_verified_at) {
                $reasons[] = 'Vendor email is not verified.';
            }
            if (! empty($vendor->ad_restricted)) {
                $reasons[] = 'Vendor advertising account is restricted.';
            }
        }

        if ($campaign->placement_id && $campaign->placement?->isFull()) {
            $reasons[] = 'The selected placement is at capacity.';
        }

        return [
            'passed' => empty($reasons),
            'reasons' => $reasons,
        ];
    }

    /**
     * Move a draft (or changed) campaign into the review queue.
     * Campaigns are never approved until their payment has been verified.
     */
    public function submit(AdCampaign $campaign): void
    {
        $from = $campaign->status;
        $campaign->status = 'submitted';
        $campaign->submitted_at = now();
        $campaign->save();

        $this->log($campaign, 'submitted', $from, 'submitted', 'Campaign submitted for review.');

        if (! $this->isPaid($campaign)) {
            $campaign->status = 'awaiting_payment';
            $campaign->save();
            $this->log($campaign, 'awaiting_payment', 'submitted', 'awaiting_payment', 'Awaiting payment verification.');
        } elseif (BusinessRule::truthy('ad_auto_approve', true)) {
            $this->autoApprove($campaign);
        }

        app(WorkflowDispatcher::class)->dispatchTrigger('ad_submit', [
            'campaign_id' => $campaign->id,
            'vendor_id' => $campaign->vendor_id,
            'product_id' => $campaign->product_id,
        ]);
    }

    /**
     * Has the campaign's payment been verified by an admin/processor?
     */
    public function isPaid(AdCampaign $campaign): bool
    {
        return $campaign->paid_at !== null && (float) $campaign->amount_paid > 0;
    }

    /**
     * Attempt automatic approval based on rules. Returns true when approved.
     */
    public function autoApprove(AdCampaign $campaign): bool
    {
        if (! BusinessRule::truthy('ad_auto_approve', true)) {
            return false;
        }

        if (! $this->isPaid($campaign)) {
            return false;
        }

        $result = $this->evaluate($campaign);

        if (! $result['passed']) {
            $campaign->admin_note = implode(' ', $result['reasons']);
            $campaign->save();

            return false;
        }

        $campaign->auto_approved = true;
        $campaign->admin_note = 'Auto-approved: passed all business rules.';
        $campaign->save();

        $this->log($campaign, 'auto_approved', 'submitted', 'approved', 'Passed all business rules.');
        $this->activate($campaign);

        return true;
    }

    /**
     * Take a submitted campaign under review.
     */
    public function startReview(AdCampaign $campaign, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'under_review';
        $campaign->reviewed_by = $adminId ?: Auth::id();
        $campaign->save();

        $this->log($campaign, 'under_review', $from, 'under_review', 'Review started.', $adminId);
    }

    /**
     * Request changes from the vendor.
     */
    public function requestChanges(AdCampaign $campaign, ?string $note = null, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'changes_requested';
        $campaign->admin_note = $note ?: 'Changes requested.';
        $campaign->reviewed_at = now();
        $campaign->reviewed_by = $adminId ?: Auth::id();
        $campaign->save();

        $this->unsyncProduct($campaign);
        $this->log($campaign, 'changes_requested', $from, 'changes_requested', $note, $adminId);
        app(AdPolicyService::class)->review($campaign, 'changes_requested', 'policy_manual', $note, $adminId);
        app(AdAuditService::class)->logCampaign('changes_requested', $campaign, ['status' => $from], ['status' => 'changes_requested'], $note);
        $this->notifyVendor($campaign, 'changes_requested', $note);
    }

    /**
     * Mark an approved campaign live and flag its product as sponsored.
     */
    public function activate(AdCampaign $campaign): void
    {
        if (in_array($campaign->status, ['rejected', 'cancelled'], true)) {
            return;
        }

        $from = $campaign->status;
        $starts = $campaign->start_date;
        $campaign->status = (! $starts || $starts->isPast() || $starts->isToday()) ? 'active' : 'scheduled';
        $campaign->save();

        if ($campaign->status === 'active') {
            $campaign->product?->update(['is_sponsored' => true]);
        }

        $this->log($campaign, 'activated', $from, $campaign->status, 'Campaign activated.');
    }

    /**
     * Manually approve (admin action).
     */
    public function approve(AdCampaign $campaign, ?string $note = null, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->admin_note = $note ?: 'Approved by admin.';
        $campaign->auto_approved = false;
        $campaign->reviewed_at = now();
        $campaign->reviewed_by = $adminId ?: Auth::id();
        $campaign->save();

        $this->log($campaign, 'approved', $from, 'approved', $note, $adminId);
        app(AdPolicyService::class)->review($campaign, 'approved', 'approved', $note, $adminId);
        app(AdAuditService::class)->logCampaign('approved', $campaign, ['status' => $from], ['status' => 'approved'], $note);
        $this->notifyVendor($campaign, 'approved', $note);
        $this->activate($campaign);
    }

    /**
     * Reject (admin action) and remove sponsorship from the product.
     */
    public function reject(AdCampaign $campaign, ?string $note = null, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'rejected';
        $campaign->admin_note = $note ?: 'Rejected by admin.';
        $campaign->reviewed_at = now();
        $campaign->reviewed_by = $adminId ?: Auth::id();
        $campaign->save();

        $this->unsyncProduct($campaign);
        $this->log($campaign, 'rejected', $from, 'rejected', $note, $adminId);
        app(AdPolicyService::class)->review($campaign, 'rejected', 'policy_manual', $note, $adminId);
        app(AdAuditService::class)->logCampaign('rejected', $campaign, ['status' => $from], ['status' => 'rejected'], $note);
        $this->notifyVendor($campaign, 'rejected', $note);
    }

    public function pause(AdCampaign $campaign, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'paused';
        $campaign->save();
        $this->log($campaign, 'paused', $from, 'paused', null, $adminId);
    }

    public function resume(AdCampaign $campaign, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $this->activate($campaign);
        $this->log($campaign, 'resumed', $from, $campaign->status, null, $adminId);
    }

    public function complete(AdCampaign $campaign, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'completed';
        $campaign->save();
        $this->unsyncProduct($campaign);
        $this->log($campaign, 'completed', $from, 'completed', null, $adminId);
        $this->notifyVendor($campaign, 'completed');
    }

    public function cancel(AdCampaign $campaign, ?string $note = null, ?int $adminId = null): void
    {
        $from = $campaign->status;
        $campaign->status = 'cancelled';
        $campaign->admin_note = $note ?: $campaign->admin_note;
        $campaign->save();
        $this->unsyncProduct($campaign);
        $this->log($campaign, 'cancelled', $from, 'cancelled', $note, $adminId);
    }

    public function unsyncProduct(AdCampaign $campaign): void
    {
        $stillSponsored = AdCampaign::where('product_id', $campaign->product_id)
            ->whereIn('status', ['active', 'scheduled'])
            ->where('id', '!=', $campaign->id)
            ->exists();

        if (! $stillSponsored) {
            $campaign->product?->update(['is_sponsored' => false]);
        }
    }

    public function moderationLog(AdCampaign $campaign, string $action, ?string $note = null): void
    {
        $this->log($campaign, $action, $campaign->status, $campaign->status, $note);
    }

    protected function notifyVendor(AdCampaign $campaign, string $event, ?string $note = null): void
    {
        try {
            $vendor = $campaign->vendor;
            if ($vendor && method_exists($vendor, 'notify')) {
                $vendor->notify(new \App\Notifications\AdCampaignStatusNotification($campaign, $event, $note));
            }
        } catch (\Throwable $e) {
            // Notifications must never break the moderation flow.
        }
    }

    protected function log(AdCampaign $campaign, string $action, ?string $from, ?string $to, ?string $note = null, ?int $adminId = null): void
    {
        AdModerationLog::create([
            'campaign_id' => $campaign->id,
            'admin_id' => $adminId ?: Auth::id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }
}
