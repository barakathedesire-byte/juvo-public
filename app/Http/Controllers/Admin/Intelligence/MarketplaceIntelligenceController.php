<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\MarketplaceAnomaly;
use App\Models\MarketplaceAnomalyRule;
use App\Models\MarketplaceMetricSnapshot;
use App\Models\ProductAssociation;
use App\Services\Intelligence\AnomalyDetectionService;
use App\Services\Intelligence\MarketplaceIntelligenceService;
use App\Services\Intelligence\ProductAssociationService;
use App\Services\Intelligence\RecommendationAnalyticsService;
use App\Services\Intelligence\RecommendationTelemetry;
use Illuminate\Http\Request;

class MarketplaceIntelligenceController extends Controller
{
    public function index(Request $request, MarketplaceIntelligenceService $intelligence, RecommendationAnalyticsService $analytics, RecommendationTelemetry $telemetry)
    {
        $days = (int) $request->input('days', 30);
        $from = now()->subDays($days);

        $metrics = MarketplaceMetricSnapshot::where('period_date', '>=', $from->toDateString())
            ->orderBy('period_date')
            ->get()
            ->groupBy('metric');

        return view('admin.intelligence.marketplace.index', [
            'metricLabels' => MarketplaceIntelligenceService::METRICS,
            'metrics' => $metrics,
            'totals' => $analytics->totals($from, now()),
            'surfaceMetrics' => $analytics->bySurface($from, now()),
            'telemetry' => $telemetry->daily($days),
            'freshness' => $analytics->freshness(),
            'openAnomalies' => MarketplaceAnomaly::open()->orderByDesc('detected_at')->limit(10)->get(),
            'associations' => [
                'similar' => ProductAssociation::where('type', 'similar')->count(),
                'complementary' => ProductAssociation::where('type', 'complementary')->count(),
                'last_computed' => ProductAssociation::max('computed_at'),
            ],
            'days' => $days,
        ]);
    }

    public function rebuildAssociations(Request $request, ProductAssociationService $associations)
    {
        $minSupport = max(1, (int) $request->input('min_support', 2));
        $result = $associations->recompute($minSupport);

        \App\Services\Admin\AuditLogger::record('associations.rebuilt', null, [], array_merge($result, ['min_support' => $minSupport]));

        return back()->with(
            'success',
            "Associations rebuilt (min support {$minSupport}): {$result['similar']} similar, {$result['complementary']} complementary."
        );
    }

    public function anomalies(Request $request)
    {
        $anomalies = MarketplaceAnomaly::with('rule')
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('severity'), fn ($q, $s) => $q->where('severity', $s))
            ->orderByDesc('detected_at')
            ->paginate(30);

        return view('admin.intelligence.marketplace.anomalies', [
            'anomalies' => $anomalies,
            'rules' => MarketplaceAnomalyRule::orderBy('name')->get(),
            'statuses' => MarketplaceAnomaly::STATUSES,
        ]);
    }

    public function acknowledge(Request $request, MarketplaceAnomaly $anomaly)
    {
        $anomaly->update([
            'status' => $anomaly->assigned_to ? 'assigned' : 'acknowledged',
            'acknowledged_at' => $anomaly->acknowledged_at ?? now(),
        ]);

        \App\Services\Admin\AuditLogger::record('anomaly.acknowledged', $anomaly, [], ['status' => $anomaly->status]);

        return back()->with('success', 'Anomaly acknowledged.');
    }

    public function assign(Request $request, MarketplaceAnomaly $anomaly)
    {
        $data = $request->validate(['assigned_to' => 'required|integer|exists:users,id']);

        $anomaly->update([
            'assigned_to' => $data['assigned_to'],
            'status' => 'assigned',
            'acknowledged_at' => $anomaly->acknowledged_at ?? now(),
        ]);

        \App\Services\Admin\AuditLogger::record('anomaly.assigned', $anomaly, [], ['assigned_to' => $data['assigned_to']]);

        return back()->with('success', 'Anomaly assigned.');
    }

    public function resolve(Request $request, MarketplaceAnomaly $anomaly)
    {
        $data = $request->validate(['resolution_note' => 'nullable|string|max:1000']);

        $anomaly->update([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolution_note' => $data['resolution_note'] ?? null,
        ]);

        \App\Services\Admin\AuditLogger::record('anomaly.resolved', $anomaly, [], ['status' => 'resolved']);

        return back()->with('success', 'Anomaly resolved.');
    }

    public function falsePositive(Request $request, MarketplaceAnomaly $anomaly)
    {
        $data = $request->validate(['resolution_note' => 'nullable|string|max:1000']);

        $anomaly->update([
            'status' => 'false_positive',
            'resolved_at' => now(),
            'resolution_note' => $data['resolution_note'] ?? null,
        ]);

        \App\Services\Admin\AuditLogger::record('anomaly.false_positive', $anomaly, [], ['status' => 'false_positive']);

        return back()->with('success', 'Anomaly marked as false positive.');
    }

    public function snapshot(Request $request, MarketplaceIntelligenceService $intelligence)
    {
        $written = $intelligence->snapshot(now());
        \App\Services\Admin\AuditLogger::record('intelligence.snapshot');

        return back()->with('success', "Metric snapshots written: {$written}.");
    }

    public function detect(Request $request, AnomalyDetectionService $detector)
    {
        $result = $detector->detect(now());
        \App\Services\Admin\AuditLogger::record('intelligence.detect', null, [], $result);

        return back()->with('success', "{$result['evaluated']} rules evaluated, {$result['detected']} anomalies detected.");
    }
}
