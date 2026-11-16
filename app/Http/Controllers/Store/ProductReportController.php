<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\ProductReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ProductReportController extends Controller
{
    public function store(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'issue_type' => 'required|string|max:100',
            'description' => 'nullable|string|max:2000',
            'email' => 'nullable|email|max:190',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->with('report_error', true);
        }

        $product = \App\Models\Product::where('slug', $slug)->firstOrFail();
        $customer = Auth::guard('customer')->user();

        ProductReport::create([
            'product_id' => $product->id,
            'customer_id' => $customer?->id,
            'issue_type' => $request->input('issue_type'),
            'description' => $request->input('description'),
            'reporter_name' => $customer?->name,
            'reporter_email' => $customer?->email ?? $request->input('email'),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Thank you. Your report has been submitted.');
    }
}
