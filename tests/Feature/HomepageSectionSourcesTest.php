<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Collection;
use App\Models\HomepageSection;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class HomepageSectionSourcesTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function vendorShop(): array
    {
        $vendor = Vendor::create([
            'name' => 'Section Vendor',
            'shop_name' => 'Section Vendor Shop',
            'email' => uniqid('sec').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Section Shop',
            'slug' => 'sec-'.uniqid(),
            'status' => 'active',
        ]);

        return [$vendor, $shop];
    }

    protected function makeProduct(Vendor $vendor, Shop $shop, string $name, float $price = 5000): Product
    {
        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'sec-p-'.uniqid(),
            'category_id' => Category::first()->id,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
        ]);

        $product->translation()->create(['language_code' => 'en', 'name' => $name, 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => $price,
            'stock' => 5,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product;
    }

    public function test_collection_section_requires_at_least_one_collection(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'collection_carousel',
            'title' => 'Shop by collection',
            'status' => 'published',
            'collections' => [],
        ])->assertSessionHasErrors('collections');
    }

    public function test_collection_section_stores_slugs_and_renders_on_home(): void
    {
        $collection = Collection::create([
            'name' => 'Summer Picks XYZ',
            'slug' => 'summer-picks-'.uniqid(),
            'rule_type' => 'all',
            'status' => 'active',
        ]);

        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'collection_carousel',
            'title' => 'Browse Collections',
            'status' => 'published',
            'collections' => [$collection->slug],
            'limit' => 8,
        ])->assertRedirect();

        $section = HomepageSection::where('title', 'Browse Collections')->firstOrFail();
        $this->assertSame([$collection->slug], $section->settings['collections']);

        $response = $this->get('/')->assertOk();
        $response->assertSee('Summer Picks XYZ');
        $response->assertSee('collection='.$collection->slug, false);
    }

    public function test_collection_section_shows_every_selected_collection_even_when_limit_is_lower(): void
    {
        $collections = collect(range(1, 10))->map(function ($i) {
            return Collection::create([
                'name' => 'Selected Collection '.$i,
                'slug' => 'selected-collection-'.$i.'-'.uniqid(),
                'rule_type' => 'all',
                'status' => 'active',
            ]);
        });

        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'collection_carousel',
            'title' => 'All Selected Collections',
            'status' => 'published',
            'collections' => $collections->pluck('slug')->all(),
            'limit' => 4,
        ])->assertRedirect();

        $section = HomepageSection::where('title', 'All Selected Collections')->firstOrFail();
        $this->assertSame(10, $section->limit);

        $resolved = app(\App\Services\Homepage\HomepageService::class)
            ->sections()
            ->firstWhere('title', 'All Selected Collections');

        $this->assertCount(10, $resolved->resolved['collections']);

        $html = $this->get('/')->assertOk()->getContent();
        foreach ($collections as $collection) {
            $this->assertStringContainsString($collection->name, $html);
        }
    }

    public function test_collection_section_uses_each_collection_shape_and_size(): void
    {
        $collections = collect([
            ['name' => 'Circle Collection', 'slug' => 'circle-collection-'.uniqid(), 'shape' => 'circle', 'size' => 'lg'],
            ['name' => 'Square Collection', 'slug' => 'square-collection-'.uniqid(), 'shape' => 'square', 'size' => 'sm'],
        ])->map(fn ($data) => Collection::create($data + [
            'rule_type' => 'all',
            'status' => 'active',
        ]));

        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'collection_grid',
            'title' => 'Shaped Collections',
            'status' => 'published',
            'collections' => $collections->pluck('slug')->all(),
        ])->assertRedirect();

        $resolved = app(\App\Services\Homepage\HomepageService::class)
            ->sections()
            ->firstWhere('title', 'Shaped Collections');

        $this->assertSame(['circle', 'square'], collect($resolved->resolved['collections'])->pluck('shape')->all());
        $this->assertSame(['lg', 'sm'], collect($resolved->resolved['collections'])->pluck('size')->all());

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('jv-collection-card--circle', $html);
        $this->assertStringContainsString('jv-collection-card--square', $html);
    }

    public function test_manual_source_requires_products(): void
    {
        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'product_carousel',
            'title' => 'Handpicked',
            'source' => 'manual',
            'status' => 'published',
            'products' => [],
        ])->assertSessionHasErrors('products');
    }

    public function test_manual_source_renders_only_selected_products_in_order(): void
    {
        [$vendor, $shop] = $this->vendorShop();
        $first = $this->makeProduct($vendor, $shop, 'ManualAlphaXYZ');
        $second = $this->makeProduct($vendor, $shop, 'ManualBetaXYZ');
        $excluded = $this->makeProduct($vendor, $shop, 'ManualGammaXYZ');

        $this->actingAs($this->admin())->post('/admin/homepage/sections', [
            'type' => 'product_carousel',
            'title' => 'Editor Picks',
            'source' => 'manual',
            'layout' => 'carousel',
            'limit' => 10,
            'status' => 'published',
            'products' => [$second->id, $first->id],
        ])->assertRedirect();

        $section = HomepageSection::where('title', 'Editor Picks')->firstOrFail();
        $this->assertSame([$second->id, $first->id], $section->settings['product_ids']);

        $resolved = app(\App\Services\Homepage\HomepageService::class)
            ->sections()
            ->firstWhere('title', 'Editor Picks');

        $ids = collect($resolved->resolved['products'])->pluck('id')->all();
        $this->assertSame([$second->id, $first->id], $ids);
        $this->assertNotContains($excluded->id, $ids);
    }

    public function test_destroy_region_blocked_while_it_has_locations(): void
    {
        $region = \App\Models\Region::create(['name' => 'Delete Me', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $region->locations()->create(['name' => 'Child', 'shipping_fee' => 1000, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('admin.regions.destroy', $region))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('regions', ['id' => $region->id]);
    }

    public function test_destroy_location_removes_it(): void
    {
        $region = \App\Models\Region::create(['name' => 'Keep Me', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $location = $region->locations()->create(['name' => 'Removable', 'shipping_fee' => 1000, 'is_active' => true, 'sort_order' => 1]);

        $this->actingAs($this->admin())
            ->delete(route('admin.locations.destroy', $location))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('locations', ['id' => $location->id]);
    }
}
