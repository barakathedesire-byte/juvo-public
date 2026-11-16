<?php

namespace App\Services\Ads;

use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdOptimizationRun;
use App\Support\Ads\Money;

/**
 * Auto-bidding. Adjusts a campaign bid toward its configured objective while
 * never breaching the maximum CPC or the remaining budget. Every run is
 * recorded with the before/after bid and the metrics it was based on.
 */
class AdOptimizationService
{
    public const ALGORITHM_VERSION = 'autobid-v1';

    public function optimize(AdCampaign $campaign): AdOptimizationRun
    {
        $before = (int) $campaign->bid_minor;
        $maxBid = (int) ($campaign->max_cpc_minor ?: $campaign->account?->max_cpc_minor ?: 0);
        $strategy = $campaign->account?->auto_bid_strategy ?: 'max_clicks';

        if ((int) $campaign->amount_paid_minor <= 0 || $before <= 0) {
            return $this->record($campaign, $strategy, $before, $before, [], 'skipped', 'no_bid_or_budget');
        }

        if (! $campaign->auto_bid_enabled && ! in_array($campaign->bid_type, ['auto_cpc', 'target_cpa', 'target_roas'], true)) {
            return $this->record($campaign, $strategy, $before, $before, [], 'skipped', 'auto_bid_disabled');
        }

        if ($campaign->budgetRemainingMinor() <= 0) {
            return $this->record($campaign, $strategy, $before, $before, [], 'skipped', 'budget_exhausted');
        }

        $metrics = $this->metrics($campaign);
        $after = $this->nextBid($campaign, $strategy, $before, $maxBid, $metrics);

        $after = max(1, $after);
        if ($maxBid > 0) {
            $after = min($after, $maxBid);
        }
        $after = min($after, max(1, $campaign->budgetRemainingMinor()));

        $reason = $after === $before ? 'no_change' : 'adjusted';

        if ($after !== $before) {
            $campaign->bid_minor = $after;
            $campaign->save();
        }

        return $this->record($campaign, $strategy, $before, $after, $metrics, 'applied', $reason);
    }

    protected function nextBid(AdCampaign $campaign, string $strategy, int $before, int $maxBid, array $metrics): int
    {
        return match ($strategy) {
            'target_cpa' => $this->bidForTargetCpa($campaign, $before, $metrics),
            'target_roas' => $this->bidForTargetRoas($campaign, $before, $metrics),
            default => $this->bidForClicks($campaign, $before, $metrics),
        };
    }

    protected function bidForClicks(AdCampaign $campaign, int $before, array $metrics): int
    {
        if ($metrics['impressions'] < 100) {
            return $before;
        }

        $ctr = $metrics['ctr'];

        if ($ctr >= 0.03) {
            return (int) round($before * 1.15);
        }

        if ($ctr < 0.01) {
            return (int) round($before * 0.85);
        }

        return $before;
    }

    protected function bidForTargetCpa(AdCampaign $campaign, int $before, array $metrics): int
    {
        $target = (int) ($campaign->target_cpa_minor ?: $campaign->account?->target_cpa_minor ?: 0);

        if ($target <= 0 || $metrics['conversions'] <= 0) {
            return $before;
        }

        $cpa = intdiv($metrics['spend_minor'], $metrics['conversions']);

        if ($cpa > $target) {
            return (int) round($before * 0.85);
        }

        return (int) round($before * 1.1);
    }

    protected function bidForTargetRoas(AdCampaign $campaign, int $before, array $metrics): int
    {
        $targetRoas = (float) ($campaign->target_roas ?: $campaign->account?->target_roas ?: 0);

        if ($targetRoas <= 0 || $metrics['spend_minor'] <= 0) {
            return $before;
        }

        $roas = app(AdAttributionService::class)->attributedRevenueMinor($campaign) / $metrics['spend_minor'];

        if ($roas < $targetRoas) {
            return (int) round($before * 0.85);
        }

        return (int) round($before * 1.1);
    }

    protected function metrics(AdCampaign $campaign): array
    {
        $stats = AdDailyStat::where('campaign_id', $campaign->id)
            ->whereDate('date', '>=', today()->subDays(7))
            ->get();

        $impressions = (int) $stats->sum('impressions');
        $clicks = (int) $stats->sum('clicks');
        $conversions = (int) $stats->sum('conversions');
        $spendMinor = (int) $stats->sum('spend_minor');
        if ($spendMinor === 0) {
            $spendMinor = Money::minor($stats->sum('spend'));
        }

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'conversions' => $conversions,
            'spend_minor' => $spendMinor,
            'ctr' => $impressions > 0 ? $clicks / $impressions : 0.0,
        ];
    }

    protected function record(AdCampaign $campaign, string $strategy, int $before, int $after, array $metrics, string $status, string $reason): AdOptimizationRun
    {
        return AdOptimizationRun::create([
            'campaign_id' => $campaign->id,
            'algorithm_version' => self::ALGORITHM_VERSION,
            'strategy' => $strategy,
            'before_bid_minor' => $before,
            'after_bid_minor' => $after,
            'metrics' => $metrics,
            'status' => $status,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
