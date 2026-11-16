<?php

namespace App\Services\Ads;

use App\Models\AdAttributionTouchpoint;
use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Support\Ads\Money;
use Illuminate\Support\Str;

/**
 * Records ad touchpoints and reconciles them against real orders.
 *
 * Attribution is idempotent per (order, campaign, model) so an order is never
 * credited twice, and revenue is only counted from actual order lines.
 */
class AdAttributionService
{
    public function recordClick(AdCampaign $campaign, array $context = []): AdAttributionTouchpoint
    {
        return $this->touch($campaign, 'click', $context);
    }

    public function recordImpressionTouch(AdCampaign $campaign, array $context = []): AdAttributionTouchpoint
    {
        return $this->touch($campaign, 'impression', $context);
    }

    public function recordConversion(AdCampaign $campaign, array $context = []): AdAttributionTouchpoint
    {
        return $this->touch($campaign, 'conversion', $context);
    }

    protected function touch(AdCampaign $campaign, string $eventType, array $context = []): AdAttributionTouchpoint
    {
        $window = max(1, (int) ($campaign->attribution_window_days ?: 7));

        return AdAttributionTouchpoint::create([
            'campaign_id' => $campaign->id,
            'product_id' => $campaign->product_id,
            'event_type' => $eventType,
            'model' => 'last_click',
            'click_id' => $context['click_id'] ?? null,
            'revenue_minor' => 0,
            'currency' => $campaign->spendCurrency(),
            'status' => 'pending',
            'reference' => 'attr:'.$eventType.':'.$campaign->id.':'.($context['click_id'] ?? Str::uuid()),
            'occurred_at' => now(),
            'expires_at' => now()->addDays($window),
        ]);
    }

    /**
     * Attribute an order to the most recent pending click touchpoint on any of
     * the order's products, within each campaign's attribution window.
     *
     * @return array<int, AdAttributionTouchpoint>
     */
    public function attributeOrder(Order $order): array
    {
        $attributed = [];

        foreach (OrderDetail::where('order_id', $order->id)->get() as $detail) {
            $productId = $detail->product_id;
            if (! $productId) {
                continue;
            }

            $touchpoint = AdAttributionTouchpoint::where('product_id', $productId)
                ->where('event_type', 'click')
                ->where('status', 'pending')
                ->whereNull('order_id')
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', now()))
                ->orderByDesc('occurred_at')
                ->first();

            if (! $touchpoint) {
                continue;
            }

            $already = AdAttributionTouchpoint::where('order_id', $order->id)
                ->where('campaign_id', $touchpoint->campaign_id)
                ->where('model', $touchpoint->model)
                ->exists();

            if ($already) {
                continue;
            }

            $revenueMinor = Money::minor($detail->price) * max(1, (int) $detail->quantity);

            $touchpoint->order_id = $order->id;
            $touchpoint->order_detail_id = $detail->id;
            $touchpoint->revenue_minor = $revenueMinor;
            $touchpoint->status = 'attributed';
            $touchpoint->save();

            $attributed[] = $touchpoint;
        }

        return $attributed;
    }

    /**
     * Attributed revenue (minor units) for a campaign, from real order lines.
     */
    public function attributedRevenueMinor(AdCampaign $campaign, ?string $from = null): int
    {
        $query = AdAttributionTouchpoint::where('campaign_id', $campaign->id)
            ->where('status', 'attributed');

        if ($from) {
            $query->where('occurred_at', '>=', $from);
        }

        return (int) $query->sum('revenue_minor');
    }

    public function expireStale(): int
    {
        return AdAttributionTouchpoint::where('status', 'pending')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);
    }
}
