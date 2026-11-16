<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\VendorPayoutMethod;
use App\Models\VendorVerificationDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $vendor = Auth::guard('vendor')->user();
        $shop = Shop::where('vendor_id', $vendor->id)->first();
        $payoutMethods = VendorPayoutMethod::where('vendor_id', $vendor->id)->orderBy('id')->get();
        $documents = VendorVerificationDocument::where('vendor_id', $vendor->id)->latest()->get();

        return view('vendor.settings.index', compact('vendor', 'shop', 'payoutMethods', 'documents'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'shop_name' => 'nullable|string|max:255',
            'shop_description' => 'nullable|string',
            'opening_hours' => 'nullable|string|max:255',
            'delivery_coverage' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'profile_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $vendor = Auth::guard('vendor')->user();

        $data = $request->only([
            'name', 'phone', 'address', 'shop_name',
            'latitude', 'longitude',
        ]);

        if ($request->filled('opening_hours')) {
            $data['opening_hours'] = ['summary' => $request->input('opening_hours')];
        }
        if ($request->filled('delivery_coverage')) {
            $data['delivery_coverage'] = ['summary' => $request->input('delivery_coverage')];
        }

        if ($request->hasFile('profile_image')) {
            if ($vendor->profile_image) {
                Storage::disk('public')->delete($vendor->profile_image);
            }
            $data['profile_image'] = $request->file('profile_image')->store('vendor_profiles', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($vendor->banner) {
                Storage::disk('public')->delete($vendor->banner);
            }
            $data['banner'] = $request->file('banner')->store('vendor_banners', 'public');
        }

        $vendor->update($data);

        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => $request->shop_name ?? $vendor->shop_name ?? $vendor->name, 'status' => 'active', 'slug' => str()->slug($vendor->name.'-store')]
        );
        if ($request->filled('shop_name')) {
            $shop->name = $request->shop_name;
        }
        $shop->description = $request->input('shop_description');
        $shop->save();

        return redirect()->route('vendor.settings.index')->with('success', 'Settings saved successfully.');
    }

    public function storeDocument(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:business_license,tax_certificate,national_id,utility_bill,bank_statement,other',
            'title' => 'nullable|string|max:255',
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $vendor = Auth::guard('vendor')->user();

        $path = $request->file('document')->store('vendor_verification/'.$vendor->id, 'local');

        VendorVerificationDocument::create([
            'vendor_id' => $vendor->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'file_path' => $path,
            'disk' => 'local',
            'status' => 'pending',
        ]);

        if ($vendor->verification_status !== 'verified') {
            $vendor->update(['verification_status' => 'pending']);
        }

        return back()->with('success', 'Document uploaded. It will be reviewed by our team.');
    }

    public function destroyDocument($id)
    {
        $vendor = Auth::guard('vendor')->user();

        $document = VendorVerificationDocument::where('vendor_id', $vendor->id)->findOrFail($id);

        Storage::disk($document->disk ?: 'local')->delete($document->file_path);
        $document->delete();

        return back()->with('success', 'Document removed.');
    }

    public function storePayout(Request $request)
    {
        $request->validate([
            'method' => 'required|in:bank,mobile_money,paypal,stripe',
            'provider' => 'nullable|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        $vendor = Auth::guard('vendor')->user();

        $method = VendorPayoutMethod::create([
            'vendor_id' => $vendor->id,
            'method' => $request->method,
            'provider' => $request->provider,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'email' => $request->email,
            'is_default' => $request->boolean('is_default'),
        ]);

        if ($method->is_default) {
            VendorPayoutMethod::where('vendor_id', $vendor->id)
                ->where('id', '!=', $method->id)
                ->update(['is_default' => false]);
        }

        return redirect()->route('vendor.settings.index')->with('success', 'Payout method added.');
    }

    public function destroyPayout($id)
    {
        $vendor = Auth::guard('vendor')->user();

        $method = VendorPayoutMethod::where('vendor_id', $vendor->id)->findOrFail($id);
        $method->delete();

        return redirect()->route('vendor.settings.index')->with('success', 'Payout method removed.');
    }
}
