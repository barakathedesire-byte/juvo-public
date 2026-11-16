<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportExportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:vendor');
    }

    protected function vendorId(): int
    {
        return (int) Auth::guard('vendor')->id();
    }

    public function export()
    {
        $vendorId = $this->vendorId();

        $products = Product::where('vendor_id', $vendorId)
            ->with(['translation', 'primaryVariant', 'category.translation'])
            ->orderBy('id')
            ->get();

        $filename = 'products-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($products) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['slug', 'name', 'description', 'category', 'price', 'discount_price', 'stock', 'sku', 'barcode', 'status', 'visibility']);

            foreach ($products as $product) {
                $variant = $product->primaryVariant;
                fputcsv($out, [
                    $product->slug,
                    optional($product->translation)->name,
                    optional($product->translation)->description,
                    optional(optional($product->category)->translation)->name ?? '',
                    $variant->price ?? '',
                    $variant->discount_price ?? '',
                    $variant->stock ?? 0,
                    $variant->SKU ?? '',
                    $variant->barcode ?? '',
                    $product->status ? 'active' : 'inactive',
                    $product->visibility ?? 'public',
                ]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function sample()
    {
        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['slug', 'name', 'description', 'category', 'price', 'discount_price', 'stock', 'sku', 'barcode', 'status', 'visibility']);
            fputcsv($out, ['sample-tee', 'Sample T-Shirt', 'A soft cotton tee', 'fashion', '15000', '12000', '25', 'TEE-001', '1234567890', 'active', 'public']);
            fclose($out);
        }, 'products-template.csv', ['Content-Type' => 'text/csv']);
    }

    public function importForm()
    {
        return view('vendor.products.import');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:4096',
        ]);

        $vendorId = $this->vendorId();
        $shopId = optional(Auth::guard('vendor')->user()->shop)->id ?? \App\Models\Shop::query()->value('id');

        if (! $shopId) {
            return back()->with('error', 'Create your store profile before importing products.');
        }

        $handle = fopen($request->file('file')->getRealPath(), 'r');
        if (! $handle) {
            return back()->with('error', 'Could not read the uploaded file.');
        }

        $header = fgetcsv($handle);
        if (! $header) {
            fclose($handle);

            return back()->with('error', 'The CSV file is empty.');
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $required = ['name', 'price', 'stock'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);

                return back()->with('error', "Missing required column: {$col}.");
            }
        }

        $imported = 0;
        $updated = 0;
        $errors = [];
        $rowNum = 1;
        $defaultLang = config('app.locale');

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }

            $data = array_combine(
                array_slice($header, 0, count($row)),
                array_slice($row, 0, count($header))
            ) ?: [];

            $name = trim((string) ($data['name'] ?? ''));
            $price = $data['price'] ?? null;
            $stock = $data['stock'] ?? null;
            $sku = trim((string) ($data['sku'] ?? ''));

            if ($name === '' || ! is_numeric($price) || ! is_numeric($stock)) {
                $errors[] = "Row {$rowNum}: name, price and stock are required and must be valid.";

                continue;
            }

            $categoryId = $this->resolveCategoryId($data['category'] ?? null);
            if (! $categoryId) {
                $errors[] = "Row {$rowNum}: category '".($data['category'] ?? '')."' not found.";

                continue;
            }

            try {
                DB::transaction(function () use ($data, $name, $price, $stock, $sku, $categoryId, $vendorId, $shopId, $defaultLang, &$imported, &$updated) {
                    $existingVariant = $sku !== ''
                        ? ProductVariant::where('SKU', $sku)
                            ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
                            ->first()
                        : null;

                    if ($existingVariant) {
                        $product = $existingVariant->product;
                        $product->translation()->updateOrCreate(
                            ['language_code' => $defaultLang],
                            ['name' => $name, 'description' => $data['description'] ?? null]
                        );
                        $product->update([
                            'category_id' => $categoryId,
                            'status' => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                            'visibility' => in_array($data['visibility'] ?? 'public', ['public', 'hidden'], true) ? $data['visibility'] : 'public',
                        ]);
                        $existingVariant->update([
                            'price' => $price,
                            'discount_price' => is_numeric($data['discount_price'] ?? null) ? $data['discount_price'] : null,
                            'stock' => (int) $stock,
                        ]);
                        $updated++;

                        return;
                    }

                    $slug = $this->uniqueSlug($data['slug'] ?? $name);
                    $product = Product::create([
                        'shop_id' => $shopId,
                        'vendor_id' => $vendorId,
                        'slug' => $slug,
                        'category_id' => $categoryId,
                        'brand_id' => null,
                        'product_type' => 'simple',
                        'status' => ($data['status'] ?? 'active') === 'active' ? 1 : 0,
                        'approval_status' => 'pending',
                        'submitted_at' => now(),
                        'visibility' => in_array($data['visibility'] ?? 'public', ['public', 'hidden'], true) ? $data['visibility'] : 'public',
                    ]);

                    $product->translations()->create([
                        'language_code' => $defaultLang,
                        'name' => $name,
                        'description' => $data['description'] ?? null,
                    ]);

                    $product->variants()->create([
                        'variant_slug' => Str::slug($name).'-'.uniqid(),
                        'price' => $price,
                        'discount_price' => is_numeric($data['discount_price'] ?? null) ? $data['discount_price'] : null,
                        'stock' => (int) $stock,
                        'low_stock_threshold' => 5,
                        'SKU' => $sku !== '' ? $sku : 'SKU-'.Str::upper(Str::random(8)),
                        'barcode' => $data['barcode'] ?? null,
                        'is_primary' => 1,
                    ]);

                    $imported++;
                });
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum}: ".$e->getMessage();
            }
        }

        fclose($handle);

        $message = "{$imported} product(s) created, {$updated} updated.";

        return redirect()->route('vendor.products.import')
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    protected function resolveCategoryId($value): ?int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        return Category::whereHas('translation', fn ($q) => $q->where('name', $value))
            ->orWhere('slug', Str::slug($value))
            ->orWhere('id', is_numeric($value) ? (int) $value : 0)
            ->value('id');
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $i = 1;

        while (Product::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
