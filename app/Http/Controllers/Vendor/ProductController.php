<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\AttributeValue;
use App\Models\Brand;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Language;
use App\Models\Product;
use App\Models\ProductAttributeValue;
use App\Models\ProductVariant;
use App\Models\ProductImage;
use App\Services\Admin\CategoryService;
use App\Services\Catalog\ProductEligibilityService;
use App\Services\Store\InventoryService;
use App\Services\Vendor\ProductService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    protected $categoryService;

    protected $productService;

    public function __construct(
        CategoryService $categoryService,
        ProductService $productService,
        protected InventoryService $inventory,
    ) {
        $this->middleware('auth:vendor');
        $this->categoryService = $categoryService;
        $this->productService = $productService;
    }

    public function index()
    {
        return view('vendor.products.index');
    }

    public function getProducts(Request $request)
    {
        try {
            $request->merge(['vendor_id' => auth()->guard('vendor')->id()]);

            return $this->productService->getProductsForDataTable($request);
        } catch (\Exception $e) {
            \Log::error('Error fetching vendor product data: '.$e->getMessage());

            return response()->json(['error' => 'An error occurred while fetching products.'], 500);
        }
    }

    public function create()
    {
        $languages = Language::where('active', 1)->get();
        $categories = Category::with('translations')->get();
        $brands = Brand::with('translations')->get();
        $attributes = Attribute::with('values.translations')->get();

        $sizes = Attribute::where('name', 'Size')->first()?->values ?? collect();
        $colors = Attribute::where('name', 'Color')->first()?->values ?? collect();

        $attributeSizeMap = [
            'small' => AttributeValue::where('attribute_id', $sizes->firstWhere('name', 'Small')->id ?? 0)->pluck('id')->first(),
            'medium' => AttributeValue::where('attribute_id', $sizes->firstWhere('name', 'Medium')->id ?? 0)->pluck('id')->first(),
            'large' => AttributeValue::where('attribute_id', $sizes->firstWhere('name', 'Large')->id ?? 0)->pluck('id')->first(),
        ];

        return view('vendor.products.create', compact(
            'languages', 'categories', 'brands', 'attributes', 'sizes', 'colors', 'attributeSizeMap'
        ));
    }

    public function store(Request $request)
    {
        $defaultLang = config('app.locale');
        $vendorId = Auth::guard('vendor')->id();
        $shopId = optional(Auth::guard('vendor')->user()->shop)->id ?? \App\Models\Shop::query()->value('id');

        $rules = [
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096|dimensions:min_width=600,min_height=600',
            'variants' => 'required|array|min:1',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0|lte:variants.*.price',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.SKU' => 'required|string|max:255',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.dimensions' => 'nullable|string|max:255',
            'variants.*.language_code' => 'nullable|string|size:2',
            'variants.*.size_id' => 'nullable|exists:attribute_values,id',
            'variants.*.color_id' => 'nullable|exists:attribute_values,id',
            'visibility' => 'nullable|in:public,hidden',
            'published_at' => 'nullable|date',
        ];

        foreach ($request->input('translations', []) as $lang => $data) {
            $rules["translations.$lang.name"] = 'required|string|max:255';
            $rules["translations.$lang.description"] = 'required|string|min:5';
        }

        $validated = $request->validate($rules);

        $minimumImages = app(ProductEligibilityService::class)->minimumImages();
        $imageCount = $request->hasFile('images') ? count($request->file('images')) : 0;
        if ($imageCount < $minimumImages) {
            return back()->withErrors(['images' => "Please upload at least {$minimumImages} product images."])->withInput();
        }

        DB::transaction(function () use ($request, $defaultLang, $vendorId, $shopId) {
            $slug = $this->generateUniqueSlug($request->translations[$defaultLang]['name']);

            $product = Product::create([
                'shop_id' => $shopId,
                'vendor_id' => $vendorId,
                'slug' => $slug,
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'product_type' => 'variable',
                'status' => 0,
                'approval_status' => 'pending',
                'submitted_at' => now(),
                'visibility' => $request->input('visibility', 'public'),
                'published_at' => $request->filled('published_at') ? $request->date('published_at') : null,
            ]);

            foreach ($request->translations as $lang => $data) {
                $product->translations()->create([
                    'language_code' => $lang,
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'short_description' => $data['short_description'] ?? null,
                    'tags' => $data['tags'] ?? null,
                ]);
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = store_product_image($image, $request->boolean('remove_background'));
                    $product->images()->create([
                        'name' => $image->getClientOriginalName(),
                        'image_url' => $path,
                        'type' => 'thumb',
                    ]);
                }
            }

            foreach ($request->variants as $variantData) {
                $variant = $product->variants()->create([
                    'variant_slug' => Str::slug($variantData['name']).'-'.uniqid(),
                    'price' => $variantData['price'],
                    'discount_price' => $variantData['discount_price'] ?? null,
                    'stock' => $variantData['stock'],
                    'SKU' => $variantData['SKU'],
                    'barcode' => $variantData['barcode'] ?? null,
                    'weight' => $variantData['weight'] ?? null,
                    'dimensions' => $variantData['dimensions'] ?? null,
                    'is_primary' => 1,
                ]);

                $variant->translations()->create([
                    'language_code' => $variantData['language_code'] ?? $defaultLang,
                    'name' => $variantData['name'],
                ]);

                foreach (['size_id', 'color_id'] as $type) {
                    if (! empty($variantData[$type])) {
                        DB::table('product_variant_attribute_values')->insert([
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'attribute_value_id' => $variantData[$type],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        ProductAttributeValue::firstOrCreate([
                            'product_id' => $product->id,
                            'attribute_value_id' => $variantData[$type],
                        ]);
                    }
                }

                InventoryMovement::create([
                    'product_id' => $product->id,
                    'variant_id' => $variant->id,
                    'type' => 'initial',
                    'quantity' => (int) $variantData['stock'],
                    'balance_after' => (int) $variantData['stock'],
                    'reason' => 'Product created',
                    'created_by' => $vendorId,
                ]);
            }
        });

        return redirect()->route('vendor.products.index')->with('success', __('cms.products.success_create'));
    }

    public function generateUniqueSlug($name)
    {
        $slug = Str::slug($name);
        $original = $slug;
        $i = 1;
        while (Product::where('slug', $slug)->exists()) {
            $slug = $original.'-'.$i++;
        }

        return $slug;
    }

    public function edit($id)
    {
        $vendorId = auth('vendor')->id();

        $product = Product::with([
            'translations',
            'images',
            'variants.translations',
            'variants.attributeValues',
        ])
            ->where('vendor_id', $vendorId)
            ->where('id', $id)
            ->firstOrFail();

        $languages = Language::where('active', 1)->get();
        $categories = Category::with('translations')->get();
        $brands = Brand::with('translations')->get();
        $attributes = Attribute::with('values.translations')->get();

        $sizes = Attribute::where('name', 'Size')->first()?->values ?? collect();
        $colors = Attribute::where('name', 'Color')->first()?->values ?? collect();

        return view('vendor.products.edit', compact(
            'product',
            'languages',
            'categories',
            'brands',
            'attributes',
            'sizes',
            'colors'
        ));
    }

    public function update(Request $request, $id)
    {
        $vendorId = Auth::guard('vendor')->id();
        $defaultLang = config('app.locale');

        $product = Product::with(['variants.translations', 'images'])->where('vendor_id', $vendorId)->findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'translations.'.$defaultLang.'.name' => 'required|string|max:255',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096|dimensions:min_width=600,min_height=600',
            'variants' => 'required|array|min:1',
            'variants.*.name' => 'required|string|max:255',
            'variants.*.price' => 'required|numeric|min:0',
            'variants.*.discount_price' => 'nullable|numeric|min:0|lte:variants.*.price',
            'variants.*.stock' => 'required|integer|min:0',
            'variants.*.SKU' => 'required|string|max:255',
            'variants.*.barcode' => 'nullable|string|max:255',
            'variants.*.weight' => 'nullable|numeric|min:0',
            'variants.*.dimensions' => 'nullable|string|max:255',
            'variants.*.language_code' => 'nullable|string|size:2',
            'variants.*.size_id' => 'nullable|exists:attribute_values,id',
            'variants.*.color_id' => 'nullable|exists:attribute_values,id',
            'visibility' => 'nullable|in:public,hidden',
            'published_at' => 'nullable|date',
        ]);

        DB::transaction(function () use ($request, $product, $defaultLang) {
            $attributes = [
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
            ];

            if ($request->filled('visibility')) {
                $attributes['visibility'] = $request->input('visibility');
            }

            if ($request->has('published_at')) {
                $attributes['published_at'] = $request->filled('published_at')
                    ? $request->date('published_at')
                    : null;
            }

            if (in_array($product->approval_status, ['rejected', 'draft'], true)) {
                $attributes['approval_status'] = 'pending';
                $attributes['submitted_at'] = now();
                $attributes['rejection_reason'] = null;
            }

            $product->update($attributes);

            foreach ($request->translations as $lang => $data) {
                $product->translations()->updateOrCreate(
                    ['language_code' => $lang],
                    [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'short_description' => $data['short_description'] ?? null,
                        'tags' => $data['tags'] ?? null,
                    ]
                );
            }

            if ($request->has('remove_images')) {
                foreach ($request->remove_images as $imageId) {
                    $image = $product->images()->find($imageId);
                    if ($image) {
                        Storage::disk('public')->delete($image->image_url);
                        $image->delete();
                    }
                }
            }

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $path = store_product_image($image, $request->boolean('remove_background'));
                    $product->images()->create([
                        'name' => $image->getClientOriginalName(),
                        'image_url' => $path,
                        'type' => 'thumb',
                    ]);
                }
            }

            $oldStockBySku = $product->variants()->pluck('stock', 'SKU')->all();
            $oldPricesBySku = $product->variants()->get()->keyBy('SKU')->map(fn ($v) => [
                'price' => $v->price,
                'discount_price' => $v->discount_price,
            ])->all();

            $product->variants()->delete();
            DB::table('product_variant_attribute_values')->where('product_id', $product->id)->delete();
            ProductAttributeValue::where('product_id', $product->id)->delete();

            foreach ($request->variants as $variantData) {
                $variant = $product->variants()->create([
                    'variant_slug' => Str::slug($variantData['name']).'-'.uniqid(),
                    'price' => $variantData['price'],
                    'discount_price' => $variantData['discount_price'] ?? null,
                    'stock' => $variantData['stock'],
                    'SKU' => $variantData['SKU'],
                    'barcode' => $variantData['barcode'] ?? null,
                    'weight' => $variantData['weight'] ?? null,
                    'dimensions' => $variantData['dimensions'] ?? null,
                    'is_primary' => 1,
                ]);

                $oldPrice = $oldPricesBySku[$variantData['SKU']] ?? null;
                if ($oldPrice) {
                    $history = app(\App\Services\Pricing\PriceHistoryService::class);
                    $history->recordVariantChange($product, $variant->id, 'price', $oldPrice['price'], $variantData['price'], 'vendor_edit');
                    $history->recordVariantChange($product, $variant->id, 'discount_price', $oldPrice['discount_price'], $variantData['discount_price'] ?? null, 'vendor_edit');
                }

                $variant->translations()->create([
                    'language_code' => $variantData['language_code'] ?? $defaultLang,
                    'name' => $variantData['name'],
                ]);

                foreach (['size_id', 'color_id'] as $type) {
                    if (! empty($variantData[$type])) {
                        DB::table('product_variant_attribute_values')->insert([
                            'product_id' => $product->id,
                            'product_variant_id' => $variant->id,
                            'attribute_value_id' => $variantData[$type],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        ProductAttributeValue::firstOrCreate([
                            'product_id' => $product->id,
                            'attribute_value_id' => $variantData[$type],
                        ]);
                    }
                }

                $oldStock = $oldStockBySku[$variantData['SKU']] ?? null;
                $newStock = (int) $variantData['stock'];

                if ($oldStock === null) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'type' => 'initial',
                        'quantity' => $newStock,
                        'balance_after' => $newStock,
                        'reason' => 'Variant added',
                        'created_by' => Auth::guard('vendor')->id(),
                    ]);
                } elseif ((int) $oldStock !== $newStock) {
                    InventoryMovement::create([
                        'product_id' => $product->id,
                        'variant_id' => $variant->id,
                        'type' => 'adjustment',
                        'quantity' => $newStock - (int) $oldStock,
                        'balance_after' => $newStock,
                        'reason' => 'Product edited',
                        'created_by' => Auth::guard('vendor')->id(),
                    ]);
                }
            }
        });

        return redirect()->route('vendor.products.index')->with('success', __('cms.products.success_update'));
    }


    public function removeBackground(Request $request, $imageId)
    {
        $image = ProductImage::with('product')->findOrFail($imageId);
        $vendorId = Auth::guard('vendor')->id();

        abort_if(! $image->product || (int) $image->product->vendor_id !== (int) $vendorId, 403);

        $oldPath = $image->image_url;
        $fullPath = Storage::disk('public')->path($oldPath);

        if (! is_file($fullPath)) {
            return back()->with('error', 'Image file not found.');
        }

        $source = @imagecreatefromstring((string) file_get_contents($fullPath));

        if (! $source) {
            return back()->with('error', 'This image cannot be processed.');
        }

        $processed = remove_white_background($source);

        if ($processed !== $source) {
            imagedestroy($source);
            $source = $processed;
        }

        $directory = dirname($fullPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $newRelativePath = trim(dirname($oldPath), '.').'/'.pathinfo($oldPath, PATHINFO_FILENAME).'-nobg-'.Str::random(10).'.webp';
        $newRelativePath = ltrim(str_replace('//', '/', $newRelativePath), '/');
        $newFullPath = Storage::disk('public')->path($newRelativePath);

        if (! imagewebp($source, $newFullPath, 90)) {
            imagedestroy($source);

            return back()->with('error', 'Background removal failed.');
        }

        imagedestroy($source);
        Storage::disk('public')->delete($oldPath);
        $image->update(['image_url' => $newRelativePath]);

        return back()->with('success', 'Background removed successfully.');
    }

    public function destroy($id)
    {
        try {
            $vendorId = Auth::guard('vendor')->id();
            $product = Product::where('vendor_id', $vendorId)->findOrFail($id);

            $product->images()->delete();
            $product->variants()->delete();
            DB::table('product_variant_attribute_values')->where('product_id', $product->id)->delete();
            ProductAttributeValue::where('product_id', $product->id)->delete();

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => __('cms.products.success_delete'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Vendor product delete error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'An error occurred while deleting the product.',
            ]);
        }
    }

    public function updateStatus(Request $request)
    {
        $request->validate([
            'id' => 'required|exists:products,id',
            'status' => 'required|boolean',
        ]);

        $product = Product::where('vendor_id', Auth::guard('vendor')->id())->find($request->id);
        if ($product) {
            $product->status = $request->status;
            $product->save();

            return response()->json(['success' => true, 'message' => __('cms.products.status_updated')]);
        }

        return response()->json(['success' => false, 'message' => 'Product not found or access denied.']);
    }

    public function inventory($id)
    {
        $vendorId = Auth::guard('vendor')->id();

        $product = Product::with(['translations', 'variants.translations'])
            ->where('vendor_id', $vendorId)
            ->findOrFail($id);

        $movements = InventoryMovement::with('variant.translations')
            ->where('product_id', $product->id)
            ->latest()
            ->limit(100)
            ->get();

        $valuation = $product->variants->sum(
            fn ($variant) => (int) $variant->stock * (float) ($variant->discount_price ?? $variant->price)
        );

        return view('vendor.products.inventory', compact('product', 'movements', 'valuation'));
    }

    public function adjustInventory(Request $request, $id)
    {
        $vendorId = Auth::guard('vendor')->id();

        $product = Product::where('vendor_id', $vendorId)->findOrFail($id);

        $data = $request->validate([
            'variant_id' => 'required|exists:product_variants,id',
            'quantity' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255',
        ]);

        $variant = ProductVariant::where('product_id', $product->id)->findOrFail($data['variant_id']);

        $movement = $this->inventory->adjust(
            $variant,
            (int) $data['quantity'],
            $data['reason'] ?? null,
            $vendorId,
        );

        return response()->json([
            'success' => true,
            'message' => $movement ? 'Stock updated.' : 'Stock unchanged.',
            'stock' => $variant->fresh()->stock,
        ]);
    }

    public function duplicate($id)
    {
        $vendorId = Auth::guard('vendor')->id();

        $product = Product::with([
            'translations',
            'images',
            'variants.translations',
            'variants.attributeValues',
        ])->where('vendor_id', $vendorId)->findOrFail($id);

        $copy = DB::transaction(function () use ($product, $vendorId) {
            $name = $product->translations->firstWhere('language_code', config('app.locale'))?->name
                ?? $product->translations->first()?->name
                ?? $product->slug;

            $new = Product::create([
                'shop_id' => $product->shop_id,
                'vendor_id' => $vendorId,
                'slug' => $this->generateUniqueSlug($name),
                'category_id' => $product->category_id,
                'brand_id' => $product->brand_id,
                'product_type' => $product->product_type,
                'status' => 0,
                'approval_status' => 'draft',
                'visibility' => 'hidden',
            ]);

            foreach ($product->translations as $translation) {
                $new->translations()->create([
                    'language_code' => $translation->language_code,
                    'name' => $translation->name,
                    'description' => $translation->description,
                    'short_description' => $translation->short_description,
                    'tags' => $translation->tags,
                ]);
            }

            foreach ($product->images as $image) {
                $path = $image->image_url;

                if ($path && Storage::disk('public')->exists($path)) {
                    $newPath = 'products/'.uniqid().'-'.basename($path);
                    Storage::disk('public')->copy($path, $newPath);
                    $path = $newPath;
                }

                $new->images()->create([
                    'name' => $image->name,
                    'image_url' => $path,
                    'type' => $image->type,
                ]);
            }

            foreach ($product->variants as $variant) {
                $newVariant = $new->variants()->create([
                    'variant_slug' => Str::slug($variant->variant_slug).'-'.uniqid(),
                    'price' => $variant->price,
                    'discount_price' => $variant->discount_price,
                    'stock' => 0,
                    'low_stock_threshold' => $variant->low_stock_threshold,
                    'SKU' => $this->uniqueSku($variant->SKU),
                    'barcode' => $variant->barcode,
                    'weight' => $variant->weight,
                    'dimensions' => $variant->dimensions,
                    'is_primary' => $variant->is_primary,
                ]);

                foreach ($variant->translations as $translation) {
                    $newVariant->translations()->create([
                        'language_code' => $translation->language_code,
                        'name' => $translation->name,
                    ]);
                }

                foreach ($variant->attributeValues as $attributeValue) {
                    DB::table('product_variant_attribute_values')->insert([
                        'product_id' => $new->id,
                        'product_variant_id' => $newVariant->id,
                        'attribute_value_id' => $attributeValue->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    ProductAttributeValue::firstOrCreate([
                        'product_id' => $new->id,
                        'attribute_value_id' => $attributeValue->id,
                    ]);
                }
            }

            return $new;
        });

        return redirect()->route('vendor.products.edit', $copy->id)
            ->with('success', 'Product duplicated as a draft. Review and submit for approval.');
    }

    public function archive($id)
    {
        $product = Product::where('vendor_id', Auth::guard('vendor')->id())->findOrFail($id);

        if (! $product->archived_at) {
            $product->update(['archived_at' => now()]);
        }

        return back()->with('success', 'Product archived.');
    }

    public function restore($id)
    {
        $product = Product::where('vendor_id', Auth::guard('vendor')->id())->findOrFail($id);

        if ($product->archived_at) {
            $product->update(['archived_at' => null]);
        }

        return back()->with('success', 'Product restored.');
    }

    public function toggleVisibility(Request $request, $id)
    {
        $request->validate(['visibility' => 'required|in:public,hidden']);

        $product = Product::where('vendor_id', Auth::guard('vendor')->id())->findOrFail($id);
        $product->update(['visibility' => $request->visibility]);

        return response()->json([
            'success' => true,
            'visibility' => $product->visibility,
            'message' => __('cms.products.status_updated'),
        ]);
    }

    public function submitForApproval($id)
    {
        $product = Product::where('vendor_id', Auth::guard('vendor')->id())->findOrFail($id);

        $product->update([
            'approval_status' => 'pending',
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return back()->with('success', 'Product submitted for approval.');
    }

    private function uniqueSku(string $sku): string
    {
        $candidate = $sku.'-copy';
        $i = 1;

        while (ProductVariant::where('SKU', $candidate)->exists()) {
            $candidate = $sku.'-copy'.$i++;
        }

        return $candidate;
    }
}
