<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Services\Ads\AdApprovalService;
use Illuminate\Http\Request;

class SubmissionController extends Controller
{
    public function __construct(protected AdApprovalService $approval) {}

    public function index(Request $request)
    {
        $status = $request->query('status', 'submitted');

        $campaigns = AdCampaign::with(['vendor', 'product.translation', 'placement', 'package'])
            ->when($status === 'all', fn ($q) => $q, fn ($q) => $q->where('status', $status))
            ->orderByDesc('priority')
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->withQueryString();

        $queueCounts = [
            'submitted' => AdCampaign::where('status', 'submitted')->count(),
            'under_review' => AdCampaign::where('status', 'under_review')->count(),
            'changes_requested' => AdCampaign::where('status', 'changes_requested')->count(),
            'rejected' => AdCampaign::where('status', 'rejected')->count(),
        ];

        return view('admin.ad_manager.submissions_index', compact('campaigns', 'status', 'queueCounts'));
    }

    public function show(AdCampaign $campaign)
    {
        $campaign->load(['vendor', 'product.translation', 'placement', 'package', 'creatives', 'moderationLogs.admin']);

        return view('admin.ad_manager.submissions_show', compact('campaign'));
    }

    public function startReview(AdCampaign $campaign)
    {
        $this->approval->startReview($campaign);

        return back()->with('success', 'Campaign moved to review.');
    }

    public function approve(Request $request, AdCampaign $campaign)
    {
        $request->validate(['admin_note' => 'nullable|string|max:1000']);
        $this->approval->approve($campaign, $request->input('admin_note'));

        return redirect()->route('admin.ad-manager.submissions.index')
            ->with('success', 'Campaign approved and activated.');
    }

    public function reject(Request $request, AdCampaign $campaign)
    {
        $request->validate(['admin_note' => 'required|string|max:1000']);
        $this->approval->reject($campaign, $request->input('admin_note'));

        return redirect()->route('admin.ad-manager.submissions.index')
            ->with('success', 'Campaign rejected.');
    }

    public function requestChanges(Request $request, AdCampaign $campaign)
    {
        $request->validate(['admin_note' => 'required|string|max:1000']);
        $this->approval->requestChanges($campaign, $request->input('admin_note'));

        return redirect()->route('admin.ad-manager.submissions.index')
            ->with('success', 'Changes requested from vendor.');
    }
}
