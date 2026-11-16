<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class ProductEligibilityTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function vendor(array $attrs = []): Vendor
    {
        return Vendor::create(array_merge([
            'name' => 'Eligible Vendor',
            'shop_name' => 'Eligible Shop',
            'email' => 'eligible-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => 1,
        ], $attrs));
    }

    protected function product(Vendor $vendor, int $images = 3, string $approvalStatus = 'pending', int $status = 0): Product
    {
        $product = Product::create([
            'shop_id' => Shop::query()->value('id'),
            'vendor_id' => $vendor->id,
            'slug' => 'eligibility-'.uniqid(),
            'category_id' => Category::firstOrFail()->id,
            'product_type' => 'variable',
            'status' => $status,
            'approval_status' => $approvalStatus,
            'visibility' => 'public',
            'submitted_at' => now(),
        ]);

        $product->translations()->create([
            'language_code' => 'en',
            'name' => 'Eligibility Widget',
            'description' => 'Widget for eligibility tests.',
        ]);

        foreach (range(1, $images) as $i) {
            $product->images()->create([
                'name' => "eligibility-{$i}.jpg",
                'image_url' => "products/eligibility-{$i}.jpg",
                'type' => 'thumb',
            ]);
        }

        return $product;
    }

    public function test_approval_is_blocked_when_images_are_below_the_configured_minimum(): void
    {
        $admin = $this->admin();
        $product = $this->product($this->vendor(), images: 2);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.approve', $product->id))
            ->assertSessionHasErrors('product');

        $product->refresh();
        $this->assertSame('pending', $product->approval_status);
        $this->assertSame(0, (int) $product->status);
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_approval_is_blocked_when_the_vendor_is_not_active(): void
    {
        $admin = $this->admin();
        $vendor = $this->vendor(['status' => 'inactive', 'is_approved' => 0]);
        $product = $this->product($vendor, images: 3);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.approve', $product->id))
            ->assertSessionHasErrors('product');

        $product->refresh();
        $this->assertSame('pending', $product->approval_status);
        $this->assertFalse(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_approval_publishes_a_product_that_satisfies_eligibility(): void
    {
        $admin = $this->admin();
        $product = $this->product($this->vendor(), images: 3);

        $this->actingAs($admin)
            ->post(route('admin.products.approval.approve', $product->id))
            ->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('approved', $product->approval_status);
        $this->assertSame(1, (int) $product->status);
        $this->assertTrue(Product::visible()->whereKey($product->id)->exists());
    }

    public function test_product_image_observer_maintains_the_image_count(): void
    {
        $product = $this->product($this->vendor(), images: 3);

        $this->assertSame(3, (int) $product->fresh()->images_count);

        $product->images()->first()->delete();

        $this->assertSame(2, (int) $product->fresh()->images_count);
    }
}
