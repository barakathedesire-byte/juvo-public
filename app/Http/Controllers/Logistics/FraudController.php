<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Logistics\RiskCase;
use App\Models\Logistics\RiskEvidence;
use App\Services\Logistics\FraudDetectionService;
use Illuminate\Http\Request;

class FraudController extends Controller
{
    public function __construct(protected FraudDetectionService $detector) {}

    public function index(Request $request)
    {
        $this->canView();

        $query = RiskCase::with(['assignedTo', 'evidence'])->latest();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($severity = $request->string('severity')->toString()) {
            $query->where('severity', $severity);
        }
        if ($type = $request->string('anomaly_type')->toString()) {
            $query->where('anomaly_type', $type);
        }
        if ($search = $request->string('q')->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('case_number', 'like', "%{$search}%")->orWhere('title', 'like', "%{$search}%");
            });
        }

        $cases = $query->paginate(25)->withQueryString();

        $kpis = [
            'total' => RiskCase::count(),
            'open' => RiskCase::whereIn('status', ['new', 'under_review', 'investigation_in_progress', 'awaiting_evidence', 'escalated'])->count(),
            'critical' => RiskCase::where('severity', 'critical')->whereIn('status', ['new', 'under_review', 'investigation_in_progress', 'awaiting_evidence', 'escalated'])->count(),
            'high_risk_orders' => RiskCase::where('anomaly_type', 'delivery_without_pickup')->count(),
            'cod' => RiskCase::where('anomaly_type', 'like', '%cod%')->count(),
            'payout' => RiskCase::where('anomaly_type', 'like', '%payout%')->count(),
            'resolved' => RiskCase::whereIn('status', ['resolved', 'closed', 'false_positive'])->count(),
        ];

        return view('logistics.fraud.index', compact('cases', 'kpis'));
    }

    public function show(RiskCase $case)
    {
        $this->canView();
        $case->load(['assignedTo', 'evidence.createdBy']);

        return view('logistics.fraud.show', compact('case'));
    }

    public function scan()
    {
        $this->canView();
        $created = $this->detector->scan();

        return redirect()->route('logistics.fraud.index')->with('success', count($created).' new risk case(s) detected.');
    }

    public function update(Request $request, RiskCase $case)
    {
        $this->canView();
        $data = $request->validate([
            'status' => 'required|in:new,under_review,investigation_in_progress,awaiting_evidence,escalated,resolved,closed,false_positive',
            'resolution' => 'nullable|string|max:64',
            'resolution_notes' => 'nullable|string|max:5000',
        ]);

        $case->status = $data['status'];
        $case->resolution = $data['resolution'] ?? $case->resolution;
        $case->resolution_notes = $data['resolution_notes'] ?? $case->resolution_notes;
        if (in_array($data['status'], ['resolved', 'closed', 'false_positive'], true)) {
            $case->resolved_at = $case->resolved_at ?: now();
            if ($data['status'] === 'closed') {
                $case->closed_at = now();
            }
        }
        $case->save();

        return back()->with('success', 'Risk case updated.');
    }

    public function assign(Request $request, RiskCase $case)
    {
        $this->canView();
        $data = $request->validate([
            'assigned_to' => 'nullable|exists:logistics_admins,id',
        ]);
        $case->assigned_to = $data['assigned_to'] ?? null;
        $case->save();

        return back()->with('success', 'Assignment updated.');
    }

    public function evidence(Request $request, RiskCase $case)
    {
        $this->canView();
        $data = $request->validate([
            'evidence_type' => 'required|string|max:64',
            'reference' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'file' => 'nullable|file|max:5120',
        ]);

        $path = null;
        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('risk-evidence', 'public');
        }

        RiskEvidence::create([
            'case_id' => $case->id,
            'evidence_type' => $data['evidence_type'],
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'file_path' => $path,
            'created_by' => auth('logistics_admin')->id(),
        ]);

        return back()->with('success', 'Evidence added.');
    }

    private function canView(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('support') || $admin->canAccessLogisticsModule('finance')), 403);
    }
}
