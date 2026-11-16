<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdAccount;
use App\Models\AdAuctionDecision;
use App\Models\AdCampaign;
use App\Models\AdOptimizationRun;
use App\Models\AdPolicyReview;
use App\Services\Ads\AdAuditService;
use App\Services\Ads\AdLedgerService;
use App\Services\Ads\AdOptimizationService;
use App\Services\Ads\AdPolicyService;
use App\Support\Ads\Money;
use Illuminate\Http\Request;

class EngineController extends Controller
{
    public function finances()
    {
        $accounts = AdAccount::with('vendor')
            ->orderByDesc('id')
            ->paginate(20);

        $ledger = \App\Models\AdLedgerEntry::with(['campaign', 'vendor'])
            ->orderByDesc('id')
            ->paginate(30, ['*'], 'ledger');

        $summary = [
            'funded' => (int) AdAccount::sum('funded_minor'),
            'spent' => (int) AdAccount::sum('spent_minor'),
            'reserved' => (int) AdAccount::sum('reserved_minor'),
        ];

        return view('admin.ad_manager.finances_index', compact('accounts', 'ledger', 'summary'));
    }

    public function credit(Request $request, AdAccount $account, AdLedgerService $ledger)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        $amountMinor = Money::minor($data['amount']);
        $ledger->adjust($account, $amountMinor, true, null, ['reason' => $data['reason'] ?? 'admin_credit']);

        app(AdAuditService::class)->log('account_credited', [
            'account_id' => $account->id,
            'after' => ['amount_minor' => $amountMinor],
            'reason' => $data['reason'] ?? null,
        ]);

        return back()->with('success', 'Advertising account credited.');
    }

    public function auctions()
    {
        $decisions = AdAuctionDecision::orderByDesc('id')->paginate(25);

        return view('admin.ad_manager.auctions_index', compact('decisions'));
    }

    public function showAuction(AdAuctionDecision $decision, \App\Services\Ads\AdAuctionService $auction)
    {
        $explanation = $auction->explain($decision);

        return view('admin.ad_manager.auctions_show', compact('decision', 'explanation'));
    }

    public function policies()
    {
        $reviews = AdPolicyReview::with('campaign.vendor')->orderByDesc('id')->paginate(30);

        return view('admin.ad_manager.policies_index', compact('reviews'));
    }

    public function runPolicyReview(Request $request, AdCampaign $campaign, AdPolicyService $policy)
    {
        $data = $request->validate([
            'decision' => 'required|in:approved,rejected,changes_requested',
            'reason_code' => 'nullable|string|max:64',
            'note' => 'nullable|string|max:1000',
        ]);

        $policy->review($campaign, $data['decision'], $data['reason_code'] ?? null, $data['note'] ?? null);

        return back()->with('success', 'Policy review recorded.');
    }

    public function optimization()
    {
        $runs = AdOptimizationRun::with('campaign.vendor')->orderByDesc('id')->paginate(30);

        return view('admin.ad_manager.optimization_index', compact('runs'));
    }

    public function runOptimization(AdCampaign $campaign, AdOptimizationService $optimizer)
    {
        $run = $optimizer->optimize($campaign);

        return back()->with('success', 'Optimisation run recorded: '.$run->status.' ('.$run->reason.').');
    }
}
