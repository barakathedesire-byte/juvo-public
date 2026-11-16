<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    protected function vendorProduct(Vendor $vendor): Product
    {
        return Product::with('variants')->where('vendor_id', $vendor->id)->firstOrFail();
    }

    public function test_new_vendor_product_is_submitted_for_approval_and_hidden(): void
    {
        $vendor = $this->vendor();
        $category = Category::firstOrFail();
        $before = Product::visible()->count();

        Storage::fake('public');

        $this->actingAs($vendor, 'vendor')->post(route('vendor.products.store'), [
            'category_id' => $category->id,
            'visibility' => 'public',
            'images' => [
                UploadedFile::fake()->image('one.jpg'),
                UploadedFile::fake()->image('two.jpg'),
                UploadedFile::fake()->image('three.jpg'),
            ],
            'translations' => [
                'en' => ['name' => 'Lifecycle Test Widget', 'description' => 'A widget for tests.'],
            ],
            'variants' => [[
                'name' => 'Default',
                'price' => 15000,
                'stock' => 4,
                'SKU' => 'LC-WIDGET-'.uniqid(),
            ]],
        ])->assertRedirect(route('vendor.products.index'));

        $product = Product::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $product->approval_status);
        $this->assertSame(0, (int) $product->status);
        $this->assertNotNull($product->submitted_at);
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
        $this->assertSame($before, Product::visible()->count());
    }

    public function test_vendor_can_duplicate_a_product_into_a_draft(): void
    {
        $vendor = $this->vendor();
        $source = $this->vendorProduct($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.products.duplicate', $source->id));

        $copy = Product::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();

        $this->assertNotSame($source->id, $copy->id);
        $this->assertSame('draft', $copy->approval_status);
        $this->assertSame('hidden', $copy->visibility);
        $this->assertSame(0, (int) $copy->status);
        $this->assertSame($source->variants->count(), $copy->variants()->count());
        $this->assertSame(0, $copy->variants()->sum('stock'));
        $this->assertStringContainsString('-copy', $copy->variants()->first()->SKU);
        $this->assertFalse(Product::visible()->whereKey($copy->id)->exists());
    }

    public function test_archive_and_restore_control_storefront_visibility(): void
    {
        $vendor = $this->vendor();
        $product = $this->vendorProduct($vendor);

        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());

        $this->actingAs($vendor, 'vendor')->post(route('vendor.products.archive', $product->id));
        $this->assertNotNull($product->fresh()->archived_at);
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());

        $this->actingAs($vendor, 'vendor')->post(route('vendor.products.restore', $product->id));
        $this->assertNull($product->fresh()->archived_at);
        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_toggling_visibility_hides_a_product(): void
    {
        $vendor = $this->vendor();
        $product = $this->vendorProduct($vendor);

        $this->actingAs($vendor, 'vendor')
            ->post(route('vendor.products.visibility', $product->id), ['visibility' => 'hidden'])
            ->assertOk()
            ->assertJsonPath('visibility', 'hidden');

        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_resubmitting_a_rejected_product_sets_it_pending(): void
    {
        $vendor = $this->vendor();
        $product = $this->vendorProduct($vendor);
        $product->update(['approval_status' => 'rejected', 'rejection_reason' => 'Bad photos']);

        $this->actingAs($vendor, 'vendor')->post(route('vendor.products.submit', $product->id));

        $product->refresh();
        $this->assertSame('pending', $product->approval_status);
        $this->assertNull($product->rejection_reason);
        $this->assertNotNull($product->submitted_at);
    }

    public function test_scheduled_products_stay_hidden_until_published_at(): void
    {
        $vendor = $this->vendor();

        $product = Product::create([
            'shop_id' => \App\Models\Shop::query()->value('id'),
            'vendor_id' => $vendor->id,
            'slug' => 'scheduled-'.uniqid(),
            'category_id' => Category::firstOrFail()->id,
            'product_type' => 'variable',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->addDay(),
        ]);

        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());

        $product->update(['published_at' => now()->subDay()]);

        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_vendor_cannot_manage_another_vendors_product(): void
    {
        $owner = $this->vendor();
        $product = $this->vendorProduct($owner);

        $rival = Vendor::create([
            'name' => 'Rival',
            'shop_name' => 'Rival Shop',
            'email' => 'rival-lifecycle-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => 1,
        ]);

        $this->actingAs($rival, 'vendor')
            ->post(route('vendor.products.duplicate', $product->id))
            ->assertNotFound();

        $this->actingAs($rival, 'vendor')
            ->post(route('vendor.products.archive', $product->id))
            ->assertNotFound();
    }
}
