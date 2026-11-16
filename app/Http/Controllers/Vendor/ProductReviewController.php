<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ProductReviewController extends Controller
{
    public function index()
    {
        return view('vendor.reviews.index');
    }

    public function getData(Request $request)
    {
        $vendorId = auth()->guard('vendor')->id();

        $reviews = ProductReview::with(['product.translations', 'customer'])
            ->whereHas('product', function ($query) use ($vendorId) {
                $query->where('vendor_id', $vendorId);
            });

        return DataTables::of($reviews)
            ->addColumn('product_name', function ($review) {
                $lang = app()->getLocale();
                $product = $review->product;

                if (! $product) {
                    return 'N/A';
                }

                $translation = $product->translations->firstWhere('language_code', $lang);

                return $translation?->name ?? $product->translations->first()?->name ?? 'N/A';
            })
            ->addColumn('customer_name', function ($review) {
                return optional($review->customer)->name ?? 'Guest';
            })
            ->addColumn('status', function ($review) {
                return $review->is_approved
                    ? '<span class="badge bg-success">Approved</span>'
                    : '<span class="badge bg-warning text-dark">Pending</span>';
            })
            ->addColumn('action', function ($review) {
                return '
                <a href="'.route('vendor.reviews.show', $review->id).'" class="btn btn-sm btn-info">View</a>';
            })
            ->rawColumns(['status', 'action'])
            ->make(true);
    }

    public function show(ProductReview $review)
    {
        $vendorId = auth()->guard('vendor')->id();

        if (! $review->product || $review->product->vendor_id !== $vendorId) {
            abort(403, 'Unauthorized access');
        }

        $review->load(['product.translations', 'customer']);

        return view('vendor.reviews.show', compact('review'));
    }

    public function destroy(ProductReview $review)
    {
        abort(403, 'Customer reviews cannot be deleted by vendors. Use reporting/escalation instead.');
    }
}
