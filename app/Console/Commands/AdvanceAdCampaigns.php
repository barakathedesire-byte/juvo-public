<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use App\Services\Ads\AdApprovalService;
use Illuminate\Console\Command;

class AdvanceAdCampaigns extends Command
{
    protected $signature = 'ads:advance';

    protected $description = 'Activate scheduled ad campaigns and complete expired or exhausted ones.';

    public function handle(AdApprovalService $approval): int
    {
        $activated = 0;
        $completed = 0;

        AdCampaign::where('status', 'scheduled')
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
            ->get()
            ->each(function (AdCampaign $campaign) use ($approval, &$activated) {
                if ($campaign->end_date && $campaign->end_date->isPast() && ! $campaign->end_date->isToday()) {
                    return;
                }
                $approval->activate($campaign);
                $activated++;
            });

        AdCampaign::whereIn('status', ['active', 'scheduled'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', today())
            ->get()
            ->each(function (AdCampaign $campaign) use ($approval, &$completed) {
                $approval->complete($campaign);
                $completed++;
            });

        AdCampaign::where('status', 'active')
            ->where('amount_paid', '>', 0)
            ->whereColumn('spent', '>=', 'amount_paid')
            ->get()
            ->each(function (AdCampaign $campaign) use ($approval, &$completed) {
                $approval->complete($campaign);
                $completed++;
            });

        $this->info("Ad campaigns advanced: {$activated} activated, {$completed} completed.");

        return self::SUCCESS;
    }
}
