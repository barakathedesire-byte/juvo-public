<?php

namespace App\Automation\Actions;

use App\Automation\AutomationAction;
use App\Models\AdCampaign;
use App\Models\AutomationRun;
use App\Services\Ads\AdApprovalService;

/**
 * Review a freshly submitted ad campaign against the platform business rules
 * and auto-approve it when it passes. Uses the same {@see AdApprovalService}
 * the admin UI uses, so automation and human moderation can never diverge.
 */
class AdCampaignApprovalAction implements AutomationAction
{
    public function key(): string
    {
        return 'ad_campaign_approval';
    }

    public function label(): string
    {
        return 'Ad campaign approval';
    }

    public function mutates(): bool
    {
        return true;
    }

    public function handle(array $input, AutomationRun $run): array
    {
        $campaign = AdCampaign::find($input['campaign_id'] ?? null);
        if (! $campaign) {
            return ['ok' => false, 'reason' => 'campaign_not_found'];
        }

        if (in_array($campaign->status, ['approved', 'active', 'rejected', 'canceled'], true)) {
            return [
                'ok' => true,
                'outcome' => 'already_decided',
                'campaign_id' => $campaign->id,
                'status' => $campaign->status,
            ];
        }

        $approval = app(AdApprovalService::class);
        $evaluation = $approval->evaluate($campaign);
        $approved = $approval->autoApprove($campaign);

        return [
            'ok' => true,
            'outcome' => $approved ? 'approved' : 'held',
            'campaign_id' => $campaign->id,
            'passed_rules' => (bool) $evaluation['passed'],
            'reasons' => $evaluation['reasons'],
        ];
    }
}
