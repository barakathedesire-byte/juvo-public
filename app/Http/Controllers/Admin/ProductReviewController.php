<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Support\Access\AdminTabRegistry;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class ProductReviewController extends Controller
{
    public function index(Request $request)
    {
        return view('admin.reviews.index');
    }

    public function getData()
    {
        $reviews = ProductReview::with(['product.translations', 'customer']);

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
            ->addColumn('verified', function ($review) {
                return $review->is_verified_purchase
                    ? '<span class="badge bg-primary">Verified</span>'
                    : '<span class="badge bg-light text-muted">—</span>';
            })
            ->addColumn('status', function ($review) {
                return $review->is_approved
                    ? '<span class="badge bg-success">Approved</span>'
                    : '<span class="badge bg-secondary">Hidden</span>';
            })
            ->addColumn('action', function ($review) {
                $view = '<a href="'.route('admin.reviews.show', $review->id).'" class="btn btn-sm btn-info">View</a>';

                if (! AdminTabRegistry::canWrite('product-reviews')) {
                    return $view;
                }

                $toggle = $review->is_approved ? 'Hide' : 'Approve';
                $toggleClass = $review->is_approved ? 'btn-warning' : 'btn-success';

                return '
                <button type="button" class="btn btn-sm '.$toggleClass.' js-toggle-review" data-url="'.route('admin.reviews.toggle', $review->id).'">'.$toggle.'</button>
                '.$view.'
                <form action="'.route('admin.reviews.destroy', $review->id).'" method="POST" style="display:inline-block;">
                    '.csrf_field().method_field('DELETE').'
                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure?\')">Delete</button>
                </form>
            ';
            })
            ->rawColumns(['verified', 'status', 'action'])
            ->make(true);
    }

    public function toggleApproval(ProductReview $review)
    {
        abort_unless(AdminTabRegistry::canWrite('product-reviews'), 403);

        $review->update(['is_approved' => ! $review->is_approved]);

        return response()->json([
            'success' => true,
            'is_approved' => (bool) $review->is_approved,
            'message' => $review->is_approved ? 'Review approved.' : 'Review hidden.',
        ]);
    }

    public function show(ProductReview $review)
    {
        $review->load(['product.translations', 'customer']);

        return view('admin.reviews.show', compact('review'));
    }

    public function edit(ProductReview $review)
    {
        return view('admin.reviews.edit', compact('review'));
    }

    public function update(Request $request, ProductReview $review)
    {
        abort_unless(AdminTabRegistry::canWrite('product-reviews'), 403);

        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
            'is_approved' => 'boolean',
        ]);

        $data['is_approved'] = $request->boolean('is_approved');

        $review->update($data);

        return redirect()->route('admin.reviews.index')->with('success', 'Review updated successfully.');
    }

    public function destroy(ProductReview $review)
    {
        abort_unless(AdminTabRegistry::canWrite('product-reviews'), 403);

        try {
            $review->delete();

            return response()->json(['success' => true, 'message' => __('cms.product_reviews.success_delete')]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => __('cms.product_reviews.error_delete')]);
        }
    }
}
