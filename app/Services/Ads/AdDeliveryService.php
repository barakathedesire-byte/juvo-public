<?php

namespace App\Services\Ads;

use App\Models\AdAccount;
use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdEvent;
use App\Support\Ads\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdDeliveryService
{
    public function __construct(
        protected AdAuctionService $auction,
        protected AdLedgerService $ledger,
        protected AdAttributionService $attribution,
    ) {}

    /**
     * Servable campaigns for a placement, chosen by the auction.
     */
    public function forPlacement(string $key, int $limit = 1, array $context = []): Collection
    {
        return $this->auction->serve($key, array_merge($this->requestContext(), $context), $limit);
    }

    /**
     * Legacy cost helper kept for backwards compatibility (currency units).
     */
    public function eventCost(AdCampaign $campaign, string $type): float
    {
        $base = (float) ($campaign->placement?->base_price ?? 0);

        return match ($campaign->billing_model) {
            'cpc' => $type === 'click' ? $base : 0.0,
            'cpm' => $type === 'impression' ? round($base / 1000, 4) : 0.0,
            default => 0.0,
        };
    }

    public function recordImpression(AdCampaign $campaign, ?Request $request = null, array $options = []): ?AdEvent
    {
        return $this->record($campaign, 'impression', $request, $options);
    }

    public function recordClick(AdCampaign $campaign, ?Request $request = null, array $options = []): ?AdEvent
    {
        $this->attribution->recordClick($campaign, $options);

        return $this->record($campaign, 'click', $request, $options);
    }

    public function recordConversion(AdCampaign $campaign, ?Request $request = null, array $options = []): ?AdEvent
    {
        $this->attribution->recordConversion($campaign, $options);

        return $this->record($campaign, 'conversion', $request, $options);
    }

    protected function record(AdCampaign $campaign, string $type, ?Request $request, array $options): ?AdEvent
    {
        if ($campaign->status !== 'active') {
            return null;
        }

        $uid = $options['event_uid'] ?? null;
        if ($uid && ($existing = AdEvent::where('event_uid', $uid)->first())) {
            return $existing;
        }

        $invalidReason = $this->invalidReason($campaign, $type, $request, $options);
        $valid = $invalidReason === null;
        $engine = $this->engineManaged($campaign);
        $counter = match ($type) {
            'impression' => 'impressions',
            'click' => 'clicks',
            'conversion' => 'conversions',
            default => null,
        };

        $event = DB::transaction(function () use ($campaign, $type, $request, $options, $uid, $valid, $invalidReason, $engine, $counter) {
            if ($valid && $counter) {
                $campaign->increment($counter);
                $campaign->refresh();
            }

            $billable = false;
            $costMinor = 0;
            if ($valid && $counter) {
                [$billable, $costMinor] = $this->billableCost($campaign, $type, $engine);
            }

            $charged = false;
            if ($billable && $costMinor > 0) {
                if ($engine) {
                    $charged = $this->ledger->charge(
                        $campaign,
                        $costMinor,
                        'ads:spend:'.$campaign->id.':'.$type.':'.($uid ?: Str::uuid()),
                        ['event_type' => $type, 'decision_id' => $options['decision_id'] ?? null],
                    ) !== null;
                } else {
                    $charged = $this->chargeLegacy($campaign, $costMinor);
                }
            }

            $event = AdEvent::create([
                'campaign_id' => $campaign->id,
                'placement_id' => $campaign->placement_id,
                'type' => $type,
                'event_uid' => $uid,
                'decision_id' => $options['decision_id'] ?? null,
                'spend_minor' => $charged ? $costMinor : 0,
                'currency' => $campaign->spendCurrency(),
                'is_billable' => $charged,
                'is_valid' => $valid,
                'invalid_reason' => $invalidReason,
                'ip' => $request?->ip(),
                'user_agent' => substr((string) $request?->userAgent(), 0, 255),
                'meta' => $options['meta'] ?? null,
                'created_at' => now(),
            ]);

            $stat = AdDailyStat::firstOrCreate(
                ['campaign_id' => $campaign->id, 'date' => today()],
                ['impressions' => 0, 'clicks' => 0, 'conversions' => 0, 'spend' => 0, 'spend_minor' => 0, 'currency' => $campaign->spendCurrency()]
            );

            if ($valid && $counter) {
                $stat->increment($counter);
            }
            if ($charged) {
                $stat->increment('spend_minor', $costMinor);
                $stat->increment('spend', (float) Money::decimal($costMinor));
            }

            return $event;
        });

        $campaign->refresh();
        if ($campaign->amount_paid > 0 && $campaign->spent >= $campaign->amount_paid && $campaign->status === 'active') {
            app(AdApprovalService::class)->complete($campaign);
        }

        return $event;
    }

    /**
     * @return array{0: bool, 1: int} [billable, cost in minor units]
     */
    protected function billableCost(AdCampaign $campaign, string $type, bool $engine): array
    {
        if (! $engine) {
            $cost = $this->eventCost($campaign, $type);

            return [$cost > 0, Money::minor($cost)];
        }

        $bid = (int) $campaign->bid_minor;
        if ($bid <= 0) {
            $bid = (int) ($campaign->placement?->min_bid_minor ?: Money::minor($campaign->placement?->base_price ?? 0));
        }

        if ($bid <= 0) {
            return [false, 0];
        }

        $remaining = $campaign->budgetRemainingMinor();
        if ($remaining <= 0) {
            return [false, 0];
        }

        $cost = min($bid, $remaining);

        return match ($campaign->billing_model) {
            'cpc' => $type === 'click' ? [true, $cost] : [false, 0],
            'cpm' => ($type === 'impression' && $campaign->impressions % 1000 === 0) ? [true, $cost] : [false, 0],
            default => [false, 0],
        };
    }

    protected function chargeLegacy(AdCampaign $campaign, int $costMinor): bool
    {
        $cap = (float) $campaign->amount_paid;
        $current = (float) $campaign->spent;

        if ($cap > 0 && $current >= $cap) {
            return false;
        }

        $cost = (float) Money::decimal($costMinor);
        $campaign->spent = $cap > 0 ? min($current + $cost, $cap) : $current + $cost;
        $campaign->save();

        return true;
    }

    protected function engineManaged(AdCampaign $campaign): bool
    {
        if ($campaign->total_budget_minor > 0 || $campaign->amount_paid_minor > 0) {
            return true;
        }

        return AdAccount::where('vendor_id', $campaign->vendor_id)->exists();
    }

    protected function invalidReason(AdCampaign $campaign, string $type, ?Request $request, array $options): ?string
    {
        if ($options['skip_fraud'] ?? false) {
            return null;
        }

        $ua = (string) $request?->userAgent();
        if ($ua !== '' && preg_match('/bot|crawl|spider|slurp|bingpreview|headless|phantom|curl|wget|python|scrapy|facebookexternalhit/i', $ua)) {
            return 'suspected_bot';
        }

        $ip = $request?->ip();
        if ($ip) {
            $recent = AdEvent::where('campaign_id', $campaign->id)
                ->where('type', $type)
                ->where('ip', $ip)
                ->where('created_at', '>=', now()->subMinute())
                ->count();

            $limit = $type === 'click' ? 5 : 30;
            if ($recent >= $limit) {
                return 'rate_limited';
            }
        }

        return null;
    }

    protected function requestContext(): array
    {
        if (app()->runningInConsole() || ! request()->isMethod('GET')) {
            return [];
        }

        $request = request();
        $routeProduct = $request->route('product');

        return array_filter([
            'keywords' => $request->query('q') ?: $request->query('keyword'),
            'category_id' => $request->query('category_id'),
            'product_id' => (is_object($routeProduct) ? $routeProduct->id : null) ?? $request->query('product_id'),
            'vendor_id' => $request->query('vendor_id'),
            'region' => $request->query('region'),
            'device' => $this->device($request),
        ], fn ($v) => $v !== null && $v !== '');
    }

    protected function device(Request $request): ?string
    {
        $ua = strtolower((string) $request->userAgent());

        return match (true) {
            str_contains($ua, 'mobile'), str_contains($ua, 'android'), str_contains($ua, 'iphone') => 'mobile',
            str_contains($ua, 'tablet'), str_contains($ua, 'ipad') => 'tablet',
            $ua === '' => null,
            default => 'desktop',
        };
    }
}
