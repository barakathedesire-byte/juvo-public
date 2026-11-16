<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Services\Logistics\PredictiveIntelligenceService;
use Illuminate\Http\Request;

class PredictiveIntelligenceController extends Controller
{
    public function __construct(protected PredictiveIntelligenceService $intelligence) {}

    public function index(Request $request)
    {
        $this->canView();

        $days = (int) $request->input('days', 7);
        $days = max(1, min(30, $days));

        $risks = $this->intelligence->deliveryRisks(20);
        $demand = $this->intelligence->demandForecast($days);
        $capacity = $this->intelligence->riderCapacity($days);
        $failureRisks = $this->intelligence->failureRisks();
        $recommendations = $this->intelligence->recommendations();
        $alerts = \App\Models\Logistics\PredictiveAlert::latest()->limit(20)->get();

        if ($request->boolean('generate_alerts')) {
            $generated = $this->intelligence->generateAlerts();
            $alerts = \App\Models\Logistics\PredictiveAlert::latest()->limit(20)->get();
        }

        return view('logistics.predictive.index', compact('risks', 'demand', 'capacity', 'failureRisks', 'recommendations', 'alerts', 'days'));
    }

    public function acknowledge(\App\Models\Logistics\PredictiveAlert $alert)
    {
        $this->canView();
        $alert->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);

        return back()->with('success', 'Alert acknowledged.');
    }

    public function resolve(\App\Models\Logistics\PredictiveAlert $alert)
    {
        $this->canView();
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return back()->with('success', 'Alert resolved.');
    }

    private function canView(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('analytics')), 403);
    }
}
