<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CouponController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();

        $coupons = Coupon::where('vendor_id', $vendor->id)
            ->orderBy('id', 'desc')
            ->get();

        return view('vendor.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        $vendor = Auth::guard('vendor')->user();
        $data = $this->validated($request);

        Coupon::create([
            'vendor_id' => $vendor->id,
            'code' => $data['code'],
            'title' => $data['title'] ?? null,
            'type' => $data['type'],
            'discount' => $data['discount'],
            'description' => $data['description'] ?? null,
            'min_order_value' => $data['min_order_value'] ?? 0,
            'max_discount' => $data['max_discount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return redirect()->route('vendor.coupons.index')->with('success', 'Coupon created.');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);
        $vendor = Auth::guard('vendor')->user();
        $data = $this->validated($request, $coupon);

        $coupon->update([
            'code' => $data['code'],
            'title' => $data['title'] ?? null,
            'type' => $data['type'],
            'discount' => $data['discount'],
            'description' => $data['description'] ?? null,
            'min_order_value' => $data['min_order_value'] ?? 0,
            'max_discount' => $data['max_discount'] ?? null,
            'usage_limit' => $data['usage_limit'] ?? null,
            'per_customer_limit' => $data['per_customer_limit'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => (bool) ($data['is_active'] ?? false),
        ]);

        return redirect()->route('vendor.coupons.index')->with('success', 'Coupon updated.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);
        $coupon->delete();

        return redirect()->route('vendor.coupons.index')->with('success', 'Coupon deleted.');
    }

    protected function validated(Request $request, ?Coupon $coupon = null): array
    {
        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:50', 'alpha_dash',
                Rule::unique('coupons', 'code')->ignore($coupon?->id),
            ],
            'title' => 'nullable|string|max:150',
            'type' => 'required|in:percentage,fixed',
            'discount' => ['required', 'numeric', 'min:0.01', $request->input('type') === 'percentage' ? 'max:100' : 'max:100000000'],
            'description' => 'nullable|string|max:255',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_discount' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'per_customer_limit' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $data['code'] = strtoupper($data['code']);

        return $data;
    }

    protected function authorizeCoupon(Coupon $coupon): void
    {
        abort_if((int) $coupon->vendor_id !== (int) Auth::guard('vendor')->id(), 404);
    }
}
