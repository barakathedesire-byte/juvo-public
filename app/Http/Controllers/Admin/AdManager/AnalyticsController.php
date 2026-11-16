<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdPlacement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $from = $request->query('from', now()->subDays(29)->toDateString());
        $to = $request->query('to', now()->toDateString());
        $placementId = $request->query('placement_id');

        $trend = AdDailyStat::query()
            ->select('date', DB::raw('SUM(impressions) as impressions'), DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(conversions) as conversions'), DB::raw('SUM(spend) as spend'))
            ->whereBetween('date', [$from, $to])
            ->when($placementId, fn ($q) => $q->whereHas('campaign', fn ($c) => $c->where('placement_id', $placementId)))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byPlacement = AdPlacement::withCount(['campaigns'])
            ->withSum(['campaigns as impressions_sum' => fn ($q) => $q->whereBetween('created_at', [$from, $to])], 'impressions')
            ->orderBy('sort_order')
            ->get();

        $leaderboard = AdCampaign::with(['vendor', 'product.translation'])
            ->orderByDesc('impressions')
            ->limit(15)
            ->get();

        $placements = AdPlacement::orderBy('sort_order')->get();

        return view('admin.ad_manager.analytics_index', compact('trend', 'byPlacement', 'leaderboard', 'placements', 'from', 'to', 'placementId'));
    }
}
