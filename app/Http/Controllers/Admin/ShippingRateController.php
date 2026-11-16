<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ShippingRate;
use Illuminate\Http\Request;

class ShippingRateController extends Controller
{
    public function index()
    {
        $shippingRates = ShippingRate::orderBy('sort_order')->orderBy('id')->get();

        return view('admin.shipping_rates.index', compact('shippingRates'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        ShippingRate::create($data + ['is_active' => true]);

        return redirect()->route('admin.shipping-rates.index')->with('success', 'Shipping rate created.');
    }

    public function update(Request $request, ShippingRate $shippingRate)
    {
        $shippingRate->update($this->validated($request));

        return redirect()->route('admin.shipping-rates.index')->with('success', 'Shipping rate updated.');
    }

    public function toggle(ShippingRate $shippingRate)
    {
        $shippingRate->update(['is_active' => ! $shippingRate->is_active]);

        return back()->with('success', 'Shipping rate updated.');
    }

    public function destroy(ShippingRate $shippingRate)
    {
        $shippingRate->delete();

        return redirect()->route('admin.shipping-rates.index')->with('success', 'Shipping rate deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'country' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
            'min_order_value' => 'nullable|numeric|min:0',
            'max_order_value' => 'nullable|numeric|min:0',
            'rate' => 'required|numeric|min:0',
            'free_over' => 'nullable|numeric|min:0',
            'eta_label' => 'nullable|string|max:120',
            'sort_order' => 'nullable|integer|min:0',
        ]);
    }
}
