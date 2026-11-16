<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    public function index()
    {
        $currencies = Currency::orderBy('id')->get();

        return view('admin.currencies.index', compact('currencies'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies,code',
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.0001',
        ]);

        Currency::create($request->only(['name', 'code', 'symbol', 'exchange_rate']));

        return redirect()->route('admin.currencies.index')->with('success', 'Currency added successfully.');
    }

    public function update(Request $request, $id)
    {
        $currency = Currency::findOrFail($id);

        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:currencies,code,'.$currency->id,
            'symbol' => 'required|string|max:10',
            'exchange_rate' => 'required|numeric|min:0.0001',
        ]);

        $currency->update($request->only(['name', 'code', 'symbol', 'exchange_rate']));

        return redirect()->route('admin.currencies.index')->with('success', 'Currency updated successfully.');
    }

    public function destroy($id)
    {
        Currency::findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Currency deleted successfully.']);
    }
}
