<?php

namespace App\Http\Controllers\Ads;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Services\Ads\AdDeliveryService;
use Illuminate\Http\Request;

class AdTrackingController extends Controller
{
    public function __construct(protected AdDeliveryService $delivery) {}

    /**
     * 1x1 transparent tracking pixel for impressions.
     */
    public function impression(Request $request, AdCampaign $campaign)
    {
        $this->delivery->recordImpression($campaign, $request);

        $gif = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($gif, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    /**
     * Track a click and redirect to the creative target (or product page).
     */
    public function click(Request $request, AdCampaign $campaign)
    {
        $this->delivery->recordClick($campaign, $request);

        $target = $campaign->target_url ?: route('product.show', $campaign->product?->slug ?? '');

        return redirect()->away($target);
    }

    /**
     * Track a conversion (called from the merchant's thank-you flow).
     */
    public function conversion(Request $request, AdCampaign $campaign)
    {
        $this->delivery->recordConversion($campaign, $request);

        return response()->noContent();
    }
}
