<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SupportController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $reviews = ProductReview::with(['product.translation', 'customer'])
            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->orderByDesc('id')
            ->get();

        return view('vendor.support.index', compact('reviews'));
    }

    public function replyToReview(Request $request, ProductReview $review)
    {
        $vendorId = Auth::guard('vendor')->id();
        if (! $review->product || (int) $review->product->vendor_id !== $vendorId) {
            abort(403);
        }

        $data = $request->validate([
            'vendor_reply' => 'required|string|max:2000',
        ]);

        $review->update([
            'vendor_reply' => $data['vendor_reply'],
            'vendor_replied_at' => now(),
        ]);

        return back()->with('success', 'Your reply has been published.');
    }
}
