<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'all');

        $payments = AdCampaign::with(['vendor', 'product.translation'])
            ->when($status === 'paid', fn ($q) => $q->whereNotNull('paid_at'))
            ->when($status === 'unpaid', fn ($q) => $q->whereNull('paid_at'))
            ->when($status === 'awaiting', fn ($q) => $q->where('status', 'awaiting_payment'))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $summary = [
            'collected' => (float) AdCampaign::whereNotNull('paid_at')->sum('amount_paid'),
            'outstanding' => (float) AdCampaign::whereNull('paid_at')->whereNotIn('status', ['rejected', 'cancelled'])->sum('total_budget'),
            'spent' => (float) AdCampaign::sum('spent'),
        ];

        return view('admin.ad_manager.payments_index', compact('payments', 'status', 'summary'));
    }

    public function verify(AdCampaign $campaign)
    {
        app(\App\Services\Ads\AdPaymentService::class)->settle($campaign, 'Payment verified by admin.');

        return back()->with('success', 'Payment verified.');
    }

    public function invoice(AdCampaign $campaign)
    {
        $campaign->load(['vendor', 'product.translation', 'placement', 'package']);

        return view('admin.ad_manager.payments_invoice', compact('campaign'));
    }
}
