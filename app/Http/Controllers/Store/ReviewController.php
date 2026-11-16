<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(Auth::guard('customer')->check(), 403);

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string|max:500',
        ]);

        $customerId = Auth::guard('customer')->id();

        // Check if already reviewed
        if (ProductReview::where('product_id', $request->product_id)
            ->where('customer_id', $customerId)
            ->exists()) {
            return back()->with('error', __('store.product_detail.review_already_submitted'));
        }

        ProductReview::create([
            'customer_id' => $customerId,
            'product_id' => $request->product_id,
            'rating' => $request->rating,
            'review' => $request->review,
            'is_approved' => 1,
            'is_verified_purchase' => $this->isVerifiedPurchase($customerId, (int) $request->product_id),
        ]);

        return back()->with('success', __('store.product_detail.review_success'));
    }

    /**
     * A purchase is verified when the customer has a fulfilled order
     * containing this product.
     */
    protected function isVerifiedPurchase(?int $customerId, int $productId): bool
    {
        if (! $customerId) {
            return false;
        }

        return Order::where('customer_id', $customerId)
            ->whereIn('status', ['completed', 'delivered'])
            ->whereHas('details', function ($query) use ($productId) {
                $query->where('product_id', $productId);
            })
            ->exists();
    }
}
