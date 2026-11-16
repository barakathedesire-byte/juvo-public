<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorProductImportTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendorWithStore(): Vendor
    {
        $vendor = Vendor::create([
            'name' => 'Importer',
            'shop_name' => 'Importer Shop',
            'email' => uniqid('imp').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Importer Shop',
            'slug' => 'imp-'.uniqid(),
            'status' => 'active',
        ]);

        return $vendor;
    }

    protected function csvUpload(array $rows): UploadedFile
    {
        $header = ['slug', 'name', 'description', 'category', 'price', 'discount_price', 'stock', 'sku', 'barcode', 'status', 'visibility'];
        $lines = [implode(',', $header)];

        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $col) {
                $value = (string) ($row[$col] ?? '');
                $line[] = str_contains($value, ',') ? '"'.$value.'"' : $value;
            }
            $lines[] = implode(',', $line);
        }

        return UploadedFile::fake()->createWithContent('products.csv', implode("\n", $lines));
    }

    protected function makeProduct(Vendor $vendor, array $attrs = []): Product
    {
        $shop = Shop::where('vendor_id', $vendor->id)->first();
        $product = Product::create(array_merge([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'p-'.uniqid(),
            'category_id' => Category::first()->id,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ], $attrs));
        $product->translation()->create(['language_code' => 'en', 'name' => 'Existing', 'description' => 'd']);

        return $product;
    }

    public function test_export_streams_vendor_products(): void
    {
        $vendor = $this->vendorWithStore();
        $product = $this->makeProduct($vendor);
        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'EXP-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 100,
            'stock' => 3,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $response = $this->actingAs($vendor, 'vendor')->get(route('vendor.products.export'));
        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Existing', $response->streamedContent());
    }

    public function test_import_creates_products_for_own_vendor(): void
    {
        $vendor = $this->vendorWithStore();
        $category = Category::first();

        $file = $this->csvUpload([
            ['name' => 'Imported One', 'category' => $category->slug, 'price' => '2500', 'stock' => '10', 'sku' => 'IMP-1-'.uniqid()],
        ]);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.products.import.store'), ['file' => $file])
            ->assertRedirect(route('vendor.products.import'));

        $this->assertDatabaseHas('products', ['vendor_id' => $vendor->id]);
        $this->assertSame(1, Product::where('vendor_id', $vendor->id)->count());
    }

    public function test_import_updates_existing_product_by_sku(): void
    {
        $vendor = $this->vendorWithStore();
        $product = $this->makeProduct($vendor);
        $sku = 'UPD-'.uniqid();
        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => $sku,
            'low_stock_threshold' => 2,
            'price' => 100,
            'stock' => 3,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $file = $this->csvUpload([
            ['name' => 'Updated Name', 'category' => Category::first()->slug, 'price' => '999', 'stock' => '42', 'sku' => $sku],
        ]);

        $this->actingAs($vendor, 'vendor')->post(route('vendor.products.import.store'), ['file' => $file]);

        $this->assertSame(1, Product::where('vendor_id', $vendor->id)->count());
        $this->assertSame(999, (int) ProductVariant::where('SKU', $sku)->first()->price);
        $this->assertSame('Updated Name', $product->fresh()->translation->name);
    }

    public function test_import_reports_invalid_rows_and_skips_them(): void
    {
        $vendor = $this->vendorWithStore();

        $file = $this->csvUpload([
            ['name' => '', 'category' => 'nope', 'price' => 'x', 'stock' => ''],
        ]);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.products.import.store'), ['file' => $file])
            ->assertSessionHas('import_errors');

        $this->assertSame(0, Product::where('vendor_id', $vendor->id)->count());
    }

    public function test_import_cannot_hijack_another_vendors_sku(): void
    {
        $vendorA = $this->vendorWithStore();
        $vendorB = $this->vendorWithStore();

        $productB = $this->makeProduct($vendorB);
        $skuB = 'FOREIGN-'.uniqid();
        ProductVariant::create([
            'product_id' => $productB->id,
            'SKU' => $skuB,
            'low_stock_threshold' => 2,
            'price' => 100,
            'stock' => 3,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        $file = $this->csvUpload([
            ['name' => 'Stolen', 'category' => Category::first()->slug, 'price' => '1', 'stock' => '1', 'sku' => $skuB],
        ]);

        $this->actingAs($vendorA, 'vendor')->post(route('vendor.products.import.store'), ['file' => $file]);

        $this->assertSame(100, (int) ProductVariant::where('SKU', $skuB)->first()->price);
        $this->assertSame(1, Product::where('vendor_id', $vendorB->id)->count());
    }
}
