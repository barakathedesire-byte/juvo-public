<?php

namespace App\Console\Commands;

use App\Models\AdCampaign;
use App\Services\Ads\AdOptimizationService;
use Illuminate\Console\Command;

class OptimizeAdCampaigns extends Command
{
    protected $signature = 'ads:optimize {--campaign= : Limit to a single campaign id}';

    protected $description = 'Run auto-bidding optimisation for active ad campaigns.';

    public function handle(AdOptimizationService $optimizer): int
    {
        $query = AdCampaign::where('status', 'active');

        if ($id = $this->option('campaign')) {
            $query->whereKey($id);
        }

        $runs = 0;
        $adjusted = 0;

        $query->chunkById(200, function ($campaigns) use ($optimizer, &$runs, &$adjusted) {
            foreach ($campaigns as $campaign) {
                $run = $optimizer->optimize($campaign);
                $runs++;
                if ($run->status === 'applied' && $run->before_bid_minor !== $run->after_bid_minor) {
                    $adjusted++;
                }
            }
        });

        $this->info("Ad optimisation complete: {$runs} campaigns evaluated, {$adjusted} bids adjusted.");

        return self::SUCCESS;
    }
}
