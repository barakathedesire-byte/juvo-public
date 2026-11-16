<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AdminProductGalleryFlagTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function dataFor(Product $product): array
    {
        $response = $this->actingAs($this->admin())
            ->postJson(route('admin.products.data'), ['draw' => 1, 'start' => 0, 'length' => 100])
            ->assertOk();

        foreach ($response->json('data') as $row) {
            if ((int) $row['id'] === $product->id) {
                return $row;
            }
        }

        $this->fail('Product '.$product->id.' not present in admin product data.');
    }

    public function test_product_with_fewer_than_three_images_is_flagged(): void
    {
        $product = Product::visible()->firstOrFail();
        $product->images()->delete();
        $product->images()->create(['name' => 'one.jpg', 'image_url' => 'products/one.jpg', 'type' => 'thumb']);

        $row = $this->dataFor($product);

        $this->assertStringContainsString('1 / 3', $row['gallery']);
        $this->assertStringContainsString('warning', $row['gallery']);
    }

    public function test_product_with_three_or_more_images_is_not_flagged(): void
    {
        $product = Product::visible()->firstOrFail();
        $product->images()->delete();
        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $name) {
            $product->images()->create(['name' => $name, 'image_url' => 'products/'.$name, 'type' => 'thumb']);
        }

        $row = $this->dataFor($product);

        $this->assertStringNotContainsString('/ 3', $row['gallery']);
        $this->assertStringContainsString('success', $row['gallery']);
    }
}
