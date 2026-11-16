<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProductReportAdminController extends Controller
{
    public function index(Request $request)
    {
        $reports = ProductReport::with(['product', 'customer', 'reviewer'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20);

        return view('admin.product-reports.index', compact('reports'));
    }

    public function show(ProductReport $productReport)
    {
        $productReport->load(['product', 'customer', 'reviewer']);

        return view('admin.product-reports.show', compact('productReport'));
    }

    public function update(Request $request, ProductReport $productReport)
    {
        $productReport->update([
            'status' => $request->input('status', $productReport->status),
            'review_notes' => $request->input('review_notes'),
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Report updated.');
    }
}
