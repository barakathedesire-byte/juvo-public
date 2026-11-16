<?php

namespace App\Http\Controllers\Admin\AdManager;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Vendor;
use Illuminate\Http\Request;

class VendorAccountController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->query('q');

        $vendors = Vendor::query()
            ->when($search, function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('shop_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->withCount('adCampaigns')
            ->withSum('adCampaigns as ad_spend', 'amount_paid')
            ->orderByDesc('ad_spend')
            ->paginate(20)
            ->withQueryString();

        return view('admin.ad_manager.vendors_index', compact('vendors', 'search'));
    }

    public function show(Vendor $vendor)
    {
        $vendor->load([]);
        $campaigns = AdCampaign::with(['product.translation', 'placement'])
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('id')
            ->get();

        $stats = [
            'campaigns' => $campaigns->count(),
            'active' => $campaigns->where('status', 'active')->count(),
            'spend' => (float) $campaigns->sum('amount_paid'),
            'impressions' => (int) $campaigns->sum('impressions'),
            'clicks' => (int) $campaigns->sum('clicks'),
        ];

        return view('admin.ad_manager.vendors_show', compact('vendor', 'campaigns', 'stats'));
    }

    public function restrict(Vendor $vendor)
    {
        $vendor->ad_restricted = ! $vendor->ad_restricted;
        $vendor->save();

        return back()->with('success', $vendor->ad_restricted ? 'Vendor advertising restricted.' : 'Vendor advertising restored.');
    }

    public function creditLimit(Request $request, Vendor $vendor)
    {
        $request->validate(['ad_credit_limit' => 'required|numeric|min:0']);
        $vendor->ad_credit_limit = $request->input('ad_credit_limit');
        $vendor->save();

        return back()->with('success', 'Credit limit updated.');
    }
}
