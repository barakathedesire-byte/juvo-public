<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Algorithm;
use App\Models\AutomationRun;
use App\Models\MarketplaceAnomaly;
use App\Models\ProductAssociation;
use App\Services\Admin\PlatformReportService;
use App\Services\Intelligence\RecommendationAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Unified admin Command Center: one page that rolls up commerce, finance,
 * support, sales and governance signals. Individual panels are only rendered
 * when the signed-in staff member has access to the underlying module.
 */
class CommandCenterController extends Controller
{
    public function __construct(protected PlatformReportService $reports) {}

    public function index(Request $request, RecommendationAnalyticsService $discovery)
    {
        [$from, $to] = $this->range($request);

        $user = $request->user();

        $panels = [
            'commerce' => $this->canAny($user, ['orders.view', 'analytics.view', 'report.view', 'manage-sales', 'finance.view']),
            'finance' => $this->canAny($user, ['finance.view', 'manage-finances']),
            'support' => $this->canAny($user, ['support.view', 'manage-support']),
            'sales' => $this->canAny($user, ['sales.view', 'sales.manage']),
            'intelligence' => $this->canAny($user, ['algorithms.view', 'automation.view', 'intelligence.view', 'anomalies.view', 'search.view']),
            'governance' => $this->canAny($user, ['approvals.view', 'audit.view', 'features.view', 'roles.view']),
        ];

        $intelligence = null;
        if ($panels['intelligence']) {
            $totals = $discovery->totals($from, $to);

            $intelligence = [
                'algorithms_total' => Algorithm::count(),
                'algorithms_active' => Algorithm::where('status', 'active')->count(),
                'automation_dead_letter' => AutomationRun::where('status', 'dead_letter')->count(),
                'automation_retrying' => AutomationRun::where('status', 'retrying')->count(),
                'open_anomalies' => MarketplaceAnomaly::open()->count(),
                'critical_anomalies' => MarketplaceAnomaly::open()->where('severity', 'critical')->count(),
                'association_pairs' => ProductAssociation::count(),
                'impressions' => $totals['impressions'] ?? 0,
                'ctr' => $totals['ctr'] ?? 0,
                'purchases' => $totals['purchases'] ?? 0,
            ];

            if ($user->can('search.view')) {
                $searchDocs = \Illuminate\Support\Facades\Schema::hasTable('product_search_documents')
                    ? \Illuminate\Support\Facades\DB::table('product_search_documents')->count()
                    : 0;
                $searchStale = \Illuminate\Support\Facades\Schema::hasTable('product_search_documents')
                    ? \Illuminate\Support\Facades\DB::table('product_search_documents')
                        ->where(function ($q) {
                            $q->whereNull('refreshed_at')->orWhere('refreshed_at', '<', now()->subDay());
                        })->count()
                    : 0;
                $intelligence['search_index_documents'] = $searchDocs;
                $intelligence['search_index_stale'] = $searchStale;
                $intelligence['search_zero_result_anomalies'] = MarketplaceAnomaly::open()
                    ->where('metric', 'like', 'search_%')
                    ->count();
            }
        }

        return view('admin.command-center.index', [
            'from' => $from,
            'to' => $to,
            'overview' => $this->reports->overview($from, $to),
            'intelligence' => $intelligence,
            'activity' => $this->reports->activity(),
            'panels' => $panels,
        ]);
    }

    /**
     * @param  array<int, string>  $permissions
     */
    protected function canAny($user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function range(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();

        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }
}
