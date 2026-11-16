<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\AuditLogger;
use App\Services\Catalog\ProductEligibilityService;
use App\Services\Rules\RuleBlockedException;
use Illuminate\Http\Request;

class ProductApprovalController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'pending');

        $products = Product::with(['translation', 'vendor'])
            ->when($status !== 'all', fn ($q) => $q->where('approval_status', $status))
            ->orderByDesc('submitted_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $counts = Product::selectRaw('approval_status, count(*) as c')
            ->groupBy('approval_status')
            ->pluck('c', 'approval_status');

        return view('admin.products.approval', compact('products', 'status', 'counts'));
    }

    public function approve(Request $request, $id, ProductEligibilityService $eligibility, AuditLogger $audit)
    {
        $product = Product::with('vendor')->findOrFail($id);

        try {
            $eligibility->assertPublishable($product, requireImages: true);
        } catch (RuleBlockedException $e) {
            return back()->withErrors(['product' => $e->getMessage()]);
        }

        $before = $product->only(['approval_status', 'status', 'approved_by']);

        $product->update([
            'approval_status' => 'approved',
            'rejection_reason' => null,
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
            'status' => 1,
        ]);

        $audit->log('product.approved', $product, $before, $product->only(['approval_status', 'status', 'approved_by']), null, $request->user());

        return back()->with('success', 'Product "'.$this->label($product).'" approved and published.');
    }

    public function reject(Request $request, $id, AuditLogger $audit)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $product = Product::findOrFail($id);

        $before = $product->only(['approval_status', 'status']);

        $product->update([
            'approval_status' => 'rejected',
            'rejection_reason' => $data['reason'],
            'status' => 0,
        ]);

        $audit->log('product.rejected', $product, $before, $product->only(['approval_status', 'status']), $data['reason'], $request->user());

        return back()->with('success', 'Product "'.$this->label($product).'" rejected.');
    }

    protected function label(Product $product): string
    {
        return optional($product->translation)->name ?? ('#'.$product->id);
    }
}
