<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Support\Ads\Money;

/**
 * Settles the payment of an ad campaign: the paid amount is recorded, the
 * vendor's ad account is funded and the campaign is (re)run through approval.
 *
 * Used by the admin verification screen and by the mobile money callback.
 */
class AdPaymentService
{
    public function __construct(
        protected AdLedgerService $ledger,
        protected AdApprovalService $approval
    ) {}

    /**
     * Mark the campaign as paid and fund its ad account.
     */
    public function settle(AdCampaign $campaign, string $note = 'Payment verified.'): AdCampaign
    {
        // The verified amount is server-authoritative: the vendor's declared
        // budget becomes the confirmed paid amount.
        $campaign->amount_paid = (float) $campaign->total_budget;
        $campaign->paid_at = $campaign->paid_at ?: now();

        // Seed the integer-minor engine fields and fund the vendor's ad account.
        $campaign->total_budget_minor = $campaign->total_budget_minor ?: Money::minor($campaign->total_budget);
        $campaign->amount_paid_minor = $campaign->total_budget_minor;
        $campaign->daily_budget_minor = $campaign->daily_budget_minor
            ?: ($campaign->daily_budget ? Money::minor($campaign->daily_budget) : null);

        if ((int) $campaign->bid_minor <= 0 && $campaign->placement) {
            $campaign->bid_minor = (int) ($campaign->placement->min_bid_minor ?: Money::minor($campaign->placement->base_price));
        }

        $campaign->save();

        $this->ledger->fundCampaign($campaign, (int) $campaign->amount_paid_minor);
        $campaign->refresh();

        $this->approval->moderationLog($campaign, 'payment_verified', $note);

        if (! $this->approval->autoApprove($campaign)) {
            if (! in_array($campaign->status, ['rejected', 'cancelled', 'active', 'scheduled'], true)) {
                $campaign->status = 'approved';
                $campaign->save();
            }
        }

        return $campaign;
    }
}
