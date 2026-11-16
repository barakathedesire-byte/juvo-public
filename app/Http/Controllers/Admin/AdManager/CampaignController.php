<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Services\Ads\AdApprovalService;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function __construct(protected AdApprovalService $approval) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $search = $request->query('q');

        $campaigns = AdCampaign::with(['vendor', 'product.translation', 'placement'])
            ->status($status)
            ->when($search, fn ($q) => $q->where('title', 'like', "%{$search}%"))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $statuses = AdCampaign::STATUSES;

        return view('admin.ad_manager.campaigns_index', compact('campaigns', 'status', 'search', 'statuses'));
    }

    public function show(AdCampaign $campaign)
    {
        $campaign->load(['vendor', 'product.translation', 'placement', 'package', 'creatives', 'moderationLogs.admin', 'dailyStats']);

        return view('admin.ad_manager.campaigns_show', compact('campaign'));
    }

    public function pause(AdCampaign $campaign)
    {
        $this->approval->pause($campaign);

        return back()->with('success', 'Campaign paused.');
    }

    public function resume(AdCampaign $campaign)
    {
        $this->approval->resume($campaign);

        return back()->with('success', 'Campaign resumed.');
    }

    public function complete(AdCampaign $campaign)
    {
        $this->approval->complete($campaign);

        return back()->with('success', 'Campaign marked completed.');
    }

    public function cancel(Request $request, AdCampaign $campaign)
    {
        $this->approval->cancel($campaign, $request->input('admin_note'));

        return back()->with('success', 'Campaign cancelled.');
    }

    public function destroy(AdCampaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.ad-manager.campaigns.index')->with('success', 'Campaign deleted.');
    }
}
