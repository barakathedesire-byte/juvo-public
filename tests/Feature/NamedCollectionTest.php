<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageTile;
use App\Models\Product;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class NamedCollectionTest extends TestCase
{
    use DatabaseTransactions;
    use GrantsAdminAccess;

    protected function makeProduct(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'shop_id' => Shop::query()->value('id'),
            'vendor_id' => Vendor::query()->value('id'),
            'slug' => 'nc-'.uniqid(),
            'category_id' => Category::firstOrFail()->id,
            'product_type' => 'variable',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ], $attrs));
    }

    public function test_admin_can_create_a_named_collection(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.homepage.collections.store'), [
            'name' => 'Summer Sale',
            'rule_type' => 'discounted',
            'status' => 'active',
        ])->assertRedirect();

        $collection = Collection::where('slug', 'summer-sale')->firstOrFail();
        $this->assertSame('Summer Sale', $collection->name);
        $this->assertSame('active', $collection->status);
    }

    public function test_named_collection_requires_a_value_for_category_rules(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.homepage.collections.store'), [
            'name' => 'By Cat',
            'rule_type' => 'category',
            'status' => 'active',
        ])->assertSessionHasErrors('rule_value');
    }

    public function test_sponsored_rule_filters_products(): void
    {
        $sponsored = $this->makeProduct(['is_sponsored' => 1]);
        $normal = $this->makeProduct(['is_sponsored' => 0]);

        $collection = Collection::create([
            'name' => 'Featured Picks',
            'slug' => 'featured-picks-'.uniqid(),
            'rule_type' => 'sponsored',
            'status' => 'active',
        ]);

        $query = Product::query()->whereIn('id', [$sponsored->id, $normal->id]);
        $collection->applyTo($query);

        $ids = $query->pluck('id')->all();
        $this->assertContains($sponsored->id, $ids);
        $this->assertNotContains($normal->id, $ids);
    }

    public function test_category_rule_filters_products_to_the_selected_category(): void
    {
        $catA = Category::firstOrFail();
        $catB = Category::create(['slug' => 'nc-other-'.uniqid(), 'status' => 1]);

        $inA = $this->makeProduct(['category_id' => $catA->id]);
        $inB = $this->makeProduct(['category_id' => $catB->id]);

        $collection = Collection::create([
            'name' => 'Cat A',
            'slug' => 'cat-a-'.uniqid(),
            'rule_type' => 'category',
            'rule_value' => $catA->slug,
            'status' => 'active',
        ]);

        $query = Product::query()->whereIn('id', [$inA->id, $inB->id]);
        $collection->applyTo($query);

        $ids = $query->pluck('id')->all();
        $this->assertContains($inA->id, $ids);
        $this->assertNotContains($inB->id, $ids);
    }

    public function test_tile_image_upload_stores_exact_bytes_without_corruption(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $collection = Collection::create([
            'name' => 'Just Landed',
            'slug' => 'just-landed-'.uniqid(),
            'rule_type' => 'newest',
            'status' => 'active',
        ]);

        $file = UploadedFile::fake()->image('tile.png', 40, 40);

        $this->actingAs($admin)->post(route('admin.homepage.tiles.store'), [
            'title' => 'Just Landed',
            'link_type' => 'collection',
            'link_value' => $collection->slug,
            'status' => 'published',
            'image' => $file,
        ])->assertRedirect();

        $tile = HomepageTile::where('title', 'Just Landed')->latest('id')->firstOrFail();
        $this->assertNotNull($tile->image);
        Storage::disk('public')->assertExists($tile->image);

        $stored = Storage::disk('public')->get($tile->image);
        $this->assertSame(file_get_contents($file->getRealPath()), $stored);

        $info = getimagesizefromstring($stored);
        $this->assertNotFalse($info);
        $this->assertSame('image/png', $info['mime']);
    }

    public function test_tile_image_upload_rejects_non_image_files(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $file = UploadedFile::fake()->create('malicious.png', 5, 'text/plain');

        $this->actingAs($admin)->post(route('admin.homepage.tiles.store'), [
            'title' => 'Bad',
            'link_type' => 'collection',
            'link_value' => 'new_arrivals',
            'status' => 'published',
            'image' => $file,
        ])->assertSessionHasErrors('image');
    }
}
