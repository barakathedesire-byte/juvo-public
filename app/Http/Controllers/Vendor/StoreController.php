<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\Request;

class StoreController extends Controller
{
    protected function vendor(): Vendor
    {
        return auth('vendor')->user();
    }

    protected function shopFor(Vendor $vendor): Shop
    {
        return Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'name' => $vendor->shop_name ?: ($vendor->name.' Store'),
                'slug' => $this->uniqueSlug($vendor->shop_name ?? $vendor->name.'-store', $vendor->id),
                'status' => 'active',
            ]
        );
    }

    public function edit()
    {
        $vendor = auth('vendor')->user();
        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => $vendor->shop_name ?? $vendor->name, 'status' => 'active', 'slug' => str()->slug($vendor->name.'-store')]
        );

        return view('vendor.store.edit', compact('vendor', 'shop'));
    }

    public function update(Request $request)
    {
        $vendor = auth('vendor')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|alpha_dash',
            'description' => 'nullable|string|max:2000',
            'story' => 'nullable|string|max:8000',
            'support_email' => 'nullable|email|max:255',
            'support_phone' => 'nullable|string|max:40',
            'vacation_mode' => 'nullable|boolean',
            'vacation_note' => 'nullable|string|max:255',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:3072',
        ]);

        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => $validated['name'], 'status' => 'active', 'slug' => str()->slug($validated['name'])]
        );

        $shop->name = $validated['name'];
        $shop->description = $validated['description'] ?? null;
        $shop->story = $validated['story'] ?? null;
        $shop->support_email = $validated['support_email'] ?? null;
        $shop->support_phone = $validated['support_phone'] ?? null;
        $shop->vacation_mode = (bool) ($validated['vacation_mode'] ?? false);
        $shop->vacation_note = $validated['vacation_note'] ?? null;

        if (! empty($validated['slug'])) {
            $shop->slug = $this->uniqueSlug($validated['slug'], null, $shop->id);
        }

        if ($request->hasFile('logo')) {
            if ($shop->logo) {
                \Storage::disk('public')->delete($shop->logo);
            }
            $shop->logo = $request->file('logo')->store('shop_logos', 'public');
        }

        if ($request->hasFile('banner')) {
            if ($shop->banner) {
                \Storage::disk('public')->delete($shop->banner);
            }
            $shop->banner = $request->file('banner')->store('shop_banners', 'public');
        }

        $shop->save();

        return redirect()->route('vendor.store.edit')->with('success', 'Store profile saved.');
    }

    protected function uniqueSlug(string $value, ?int $vendorId = null, ?int $ignoreId = null): string
    {
        $base = \Illuminate\Support\Str::slug($value) ?: 'store';
        $slug = $base;
        $i = 1;

        while (Shop::where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
