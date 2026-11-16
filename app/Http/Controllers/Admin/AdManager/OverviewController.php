<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\AdDailyStat;
use App\Models\AdPlacement;
use Illuminate\Support\Facades\DB;

class OverviewController extends Controller
{
    public function index()
    {
        $today = now()->toDateString();

        $totals = [
            'revenue' => (float) AdCampaign::sum('amount_paid'),
            'spend' => (float) AdCampaign::sum('spent'),
            'impressions' => (int) AdCampaign::sum('impressions'),
            'clicks' => (int) AdCampaign::sum('clicks'),
            'conversions' => (int) AdCampaign::sum('conversions'),
        ];

        $counts = [
            'total' => AdCampaign::count(),
            'pending' => AdCampaign::pendingReview()->count(),
            'active' => AdCampaign::where('status', 'active')->count(),
            'scheduled' => AdCampaign::where('status', 'scheduled')->count(),
            'paused' => AdCampaign::where('status', 'paused')->count(),
            'rejected' => AdCampaign::where('status', 'rejected')->count(),
        ];

        $ctr = $totals['impressions'] > 0 ? round($totals['clicks'] / $totals['impressions'] * 100, 2) : 0;
        $cvr = $totals['clicks'] > 0 ? round($totals['conversions'] / $totals['clicks'] * 100, 2) : 0;

        $trend = AdDailyStat::query()
            ->select('date', DB::raw('SUM(impressions) as impressions'), DB::raw('SUM(clicks) as clicks'), DB::raw('SUM(spend) as spend'))
            ->where('date', '>=', now()->subDays(29)->toDateString())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $byPlacement = AdPlacement::withCount(['campaigns' => function ($q) {
            $q->where('status', 'active');
        }])->orderBy('sort_order')->get();

        $topCampaigns = AdCampaign::with(['vendor', 'product.translation'])
            ->orderByDesc('impressions')
            ->limit(8)
            ->get();

        $recentPending = AdCampaign::pendingReview()
            ->with(['vendor', 'product.translation'])
            ->orderByDesc('submitted_at')
            ->limit(6)
            ->get();

        return view('admin.ad_manager.overview', compact(
            'totals', 'counts', 'ctr', 'cvr', 'trend', 'byPlacement', 'topCampaigns', 'recentPending'
        ));
    }
}
