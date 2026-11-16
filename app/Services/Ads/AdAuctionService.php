<?php

namespace App\Services\Ads;

use App\Models\AdAuctionDecision;
use App\Models\AdCampaign;
use App\Models\AdPlacement;
use App\Models\AdTargetingRule;
use App\Support\Ads\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Deterministic, explainable ad auction.
 *
 * Candidates are scored by expected revenue per 1000 impressions (eCPM),
 * multiplied by a quality factor and a pacing multiplier. Ties break on
 * quality then campaign id so identical inputs always produce the same
 * ranking. Every decision is persisted with the full candidate list and
 * per-candidate reasons so an admin can explain any outcome.
 */
class AdAuctionService
{
    public const ALGORITHM_VERSION = 'auction-v1';

    /** pCTR assumed when a campaign has no click history yet. */
    protected float $defaultCtr = 0.02;

    public function __construct(protected AdLedgerService $ledger) {}

    public function serve(string $placementKey, array $context = [], int $limit = 1, bool $record = true): Collection
    {
        $placement = AdPlacement::where('key', $placementKey)->where('is_active', true)->first();

        if (! $placement) {
            return collect();
        }

        $requestUid = $context['request_uid'] ?? (string) Str::uuid();

        if ($record) {
            $existing = AdAuctionDecision::where('request_uid', $requestUid)->first();
            if ($existing) {
                return $this->hydrateWinners($existing, $limit);
            }
        }

        $candidates = $this->candidates($placement, $context);
        $ranked = $candidates->sort($this->rankingComparator())->values();
        $winners = $ranked->where('eligible', true)->take(max(0, $limit))->values();

        $decision = null;
        if ($record) {
            $decision = AdAuctionDecision::create([
                'request_uid' => $requestUid,
                'placement_key' => $placement->key,
                'placement_id' => $placement->id,
                'context_hash' => $this->contextHash($context),
                'algorithm_version' => self::ALGORITHM_VERSION,
                'candidate_count' => $ranked->count(),
                'winner_campaign_id' => $winners->first()['campaign_id'] ?? null,
                'winners' => $winners->map(fn ($c) => $c['campaign_id'])->all(),
                'candidates' => $ranked->all(),
                'created_at' => now(),
            ]);
        }

        return $winners->map(function ($row) use ($decision, $requestUid) {
            $campaign = AdCampaign::with(['product.translation', 'product.thumbnail', 'placement'])
                ->find($row['campaign_id']);

            if (! $campaign) {
                return null;
            }

            $campaign->setAttribute('auction_score', $row['score']);
            $campaign->setAttribute('auction_decision_id', $decision?->id);
            $campaign->setAttribute('auction_request_uid', $requestUid);

            return $campaign;
        })->filter()->values();
    }

    /**
     * All candidate campaigns for a placement, annotated with score + reasons.
     */
    public function candidates(AdPlacement $placement, array $context): Collection
    {
        $campaigns = AdCampaign::with(['product', 'account'])
            ->where('placement_id', $placement->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->where(fn ($q) => $q->whereNull('start_date')->orWhereDate('start_date', '<=', today()))
            ->get();

        $rules = AdTargetingRule::whereIn('campaign_id', $campaigns->pluck('id'))
            ->get()
            ->groupBy('campaign_id');

        return $campaigns->map(function (AdCampaign $campaign) use ($placement, $context, $rules) {
            $reasons = [];
            $eligible = true;

            if (! $campaign->product) {
                $eligible = false;
                $reasons[] = 'product_missing';
            } elseif ((int) $campaign->product->status !== 1) {
                $eligible = false;
                $reasons[] = 'product_not_published';
            }

            if ($this->remainingMinor($campaign) <= 0) {
                $eligible = false;
                $reasons[] = 'budget_exhausted';
            }

            if ($campaign->account && ! $campaign->account->isActive()) {
                $eligible = false;
                $reasons[] = 'account_'.$campaign->account->status;
            }

            $targeting = $this->matchesTargeting($rules->get($campaign->id, collect()), $context);
            if (! $targeting['ok']) {
                $eligible = false;
                $reasons = array_merge($reasons, $targeting['reasons']);
            }

            $pacing = $this->pacingMultiplier($campaign);
            if ($pacing['multiplier'] === 0.0) {
                $eligible = false;
                $reasons[] = $pacing['reason'];
            }

            $bid = $this->effectiveBidMinor($campaign, $placement);
            $ecpm = $this->expectedCpmMinor($campaign, $placement, $bid);
            $score = $ecpm * (float) $campaign->quality_factor * $pacing['multiplier'];

            return [
                'campaign_id' => $campaign->id,
                'eligible' => $eligible,
                'score' => round($score, 4),
                'bid_minor' => $bid,
                'billing_model' => $campaign->billing_model,
                'quality_factor' => (float) $campaign->quality_factor,
                'pacing_multiplier' => $pacing['multiplier'],
                'expected_cpm_minor' => $ecpm,
                'reasons' => $reasons ?: ['eligible'],
            ];
        });
    }

    public function explain(AdAuctionDecision $decision): array
    {
        return [
            'algorithm_version' => $decision->algorithm_version,
            'placement' => $decision->placement_key,
            'context_hash' => $decision->context_hash,
            'candidates' => $decision->candidates ?: [],
            'winners' => $decision->winners ?: [],
        ];
    }

    public function contextHash(array $context): string
    {
        $relevant = array_intersect_key($context, array_flip([
            'keywords', 'category_id', 'brand_id', 'product_id', 'vendor_id', 'region', 'device',
        ]));

        ksort($relevant);
        $relevant = array_map(function ($value) {
            if (is_array($value)) {
                sort($value);
            }

            return $value;
        }, $relevant);

        return sha1(json_encode($relevant));
    }

    protected function hydrateWinners(AdAuctionDecision $decision, int $limit): Collection
    {
        $ids = array_slice($decision->winners ?: [], 0, max(0, $limit));

        if (empty($ids)) {
            return collect();
        }

        $order = array_flip($ids);

        return AdCampaign::with(['product.translation', 'product.thumbnail', 'placement'])
            ->whereIn('id', $ids)
            ->get()
            ->sortBy(fn ($c) => $order[$c->id] ?? PHP_INT_MAX)
            ->values()
            ->each(function (AdCampaign $campaign) use ($decision) {
                $campaign->setAttribute('auction_decision_id', $decision->id);
                $campaign->setAttribute('auction_request_uid', $decision->request_uid);
            });
    }

    protected function rankingComparator(): \Closure
    {
        return function (array $a, array $b) {
            if ($a['eligible'] !== $b['eligible']) {
                return $b['eligible'] <=> $a['eligible'];
            }

            if ((float) $a['score'] !== (float) $b['score']) {
                return $b['score'] <=> $a['score'];
            }

            if ($a['quality_factor'] !== $b['quality_factor']) {
                return $b['quality_factor'] <=> $a['quality_factor'];
            }

            return $a['campaign_id'] <=> $b['campaign_id'];
        };
    }

    protected function remainingMinor(AdCampaign $campaign): int
    {
        if ($campaign->total_budget_minor > 0 || $campaign->amount_paid_minor > 0) {
            return $campaign->budgetRemainingMinor();
        }

        return (int) round(((float) $campaign->amount_paid - (float) $campaign->spent) * 100);
    }

    protected function effectiveBidMinor(AdCampaign $campaign, AdPlacement $placement): int
    {
        if ((int) $campaign->bid_minor > 0) {
            return (int) $campaign->bid_minor;
        }

        if ((int) $placement->min_bid_minor > 0) {
            return (int) $placement->min_bid_minor;
        }

        return Money::minor($placement->base_price);
    }

    protected function expectedCpmMinor(AdCampaign $campaign, AdPlacement $placement, int $bid): int
    {
        return match ($campaign->billing_model ?: $placement->pricing_model) {
            'cpc' => (int) round($bid * $this->ctr($campaign) * 1000),
            'flat' => 1_000_000_000,
            default => $bid,
        };
    }

    protected function ctr(AdCampaign $campaign): float
    {
        if ($campaign->impressions > 0 && $campaign->clicks > 0) {
            return $campaign->clicks / $campaign->impressions;
        }

        return $this->defaultCtr;
    }

    /** @return array{multiplier: float, reason: string} */
    protected function pacingMultiplier(AdCampaign $campaign): array
    {
        $dailyMinor = $campaign->daily_budget_minor
            ?: ($campaign->daily_budget ? Money::minor($campaign->daily_budget) : 0);

        if ($dailyMinor <= 0) {
            return ['multiplier' => 1.0, 'reason' => 'no_daily_cap'];
        }

        $todaySpent = $this->ledger->dailySpentMinor($campaign)
            + $this->ledger->dailyHeldMinor($campaign);

        if ($todaySpent >= $dailyMinor) {
            return ['multiplier' => 0.0, 'reason' => 'daily_budget_exhausted'];
        }

        if ($campaign->pacing === 'even') {
            $target = (int) floor($dailyMinor * $this->elapsedFraction());
            if ($todaySpent > $target) {
                return ['multiplier' => 0.5, 'reason' => 'ahead_of_pace'];
            }
        }

        return ['multiplier' => 1.0, 'reason' => 'on_pace'];
    }

    protected function elapsedFraction(): float
    {
        return min(1.0, max(0.0, now()->secondsSinceMidnight() / 86400));
    }

    /** @return array{ok: bool, reasons: array<int,string>} */
    public function matchesTargeting(Collection $rules, array $context): array
    {
        $reasons = [];

        foreach ($rules->where('kind', 'exclude') as $rule) {
            if ($this->ruleMatches($rule, $context)) {
                $reasons[] = 'targeting_exclude_'.$rule->dimension;
            }
        }

        foreach ($rules->where('kind', 'include')->groupBy('dimension') as $dimension => $dimensionRules) {
            if (! $dimensionRules->contains(fn ($rule) => $this->ruleMatches($rule, $context))) {
                $reasons[] = 'targeting_include_'.$dimension;
            }
        }

        return ['ok' => empty($reasons), 'reasons' => $reasons];
    }

    protected function ruleMatches(AdTargetingRule $rule, array $context): bool
    {
        $values = $this->contextValues($rule->dimension, $context)->map(fn ($v) => mb_strtolower((string) $v));
        $needle = mb_strtolower((string) $rule->value);

        return match ($rule->operator) {
            'equals' => $values->contains(fn ($v) => $v === $needle),
            'contains' => $values->contains(fn ($v) => str_contains($v, $needle)),
            'not_in' => ! $values->contains(fn ($v) => $v === $needle),
            default => $values->contains(fn ($v) => $v === $needle),
        };
    }

    protected function contextValues(string $dimension, array $context): Collection
    {
        $key = [
            'keyword' => 'keywords',
            'category' => 'category_id',
            'brand' => 'brand_id',
            'product' => 'product_id',
            'vendor' => 'vendor_id',
        ][$dimension] ?? $dimension;

        $value = $context[$key] ?? null;

        return collect(is_array($value) ? $value : ($value === null ? [] : [$value]));
    }
}
