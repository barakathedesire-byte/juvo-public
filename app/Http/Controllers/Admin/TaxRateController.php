<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TaxRate;
use Illuminate\Http\Request;

class TaxRateController extends Controller
{
    public function index()
    {
        $taxRates = TaxRate::orderByDesc('id')->get();

        return view('admin.tax_rates.index', compact('taxRates'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'rate' => 'required|numeric|min:0|max:100',
            'country' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
        ]);

        TaxRate::create($data + ['is_active' => true]);

        return redirect()->route('admin.tax-rates.index')->with('success', 'Tax rate created.');
    }

    public function update(Request $request, TaxRate $taxRate)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'rate' => 'required|numeric|min:0|max:100',
            'country' => 'nullable|string|max:120',
            'region' => 'nullable|string|max:120',
        ]);

        $taxRate->update($data);

        return redirect()->route('admin.tax-rates.index')->with('success', 'Tax rate updated.');
    }

    public function toggle(TaxRate $taxRate)
    {
        $taxRate->update(['is_active' => ! $taxRate->is_active]);

        return back()->with('success', 'Tax rate updated.');
    }

    public function destroy(TaxRate $taxRate)
    {
        $taxRate->delete();

        return redirect()->route('admin.tax-rates.index')->with('success', 'Tax rate deleted.');
    }
}
