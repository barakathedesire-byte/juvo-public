<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Region;
use App\Models\ShippingAddress;
use App\Models\Shop;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class RegionLocationTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function region(string $name, bool $active = true, int $sort = 0): Region
    {
        return Region::create([
            'name' => $name,
            'country' => 'Tanzania',
            'is_active' => $active,
            'sort_order' => $sort,
        ]);
    }

    protected function product(): Product
    {
        $vendor = Vendor::create([
            'name' => 'Region Vendor',
            'shop_name' => 'Region Vendor Shop',
            'email' => uniqid('rv').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);

        $shop = Shop::create([
            'vendor_id' => $vendor->id,
            'name' => 'Region Shop',
            'slug' => 'region-'.uniqid(),
            'status' => 'active',
        ]);

        $product = Product::create([
            'shop_id' => $shop->id,
            'vendor_id' => $vendor->id,
            'slug' => 'region-p-'.uniqid(),
            'category_id' => Category::first()->id,
            'brand_id' => null,
            'product_type' => 'simple',
            'status' => 1,
            'approval_status' => 'approved',
            'visibility' => 'public',
        ]);

        $product->translation()->create(['language_code' => 'en', 'name' => 'Region Product', 'description' => 'desc']);

        ProductVariant::create([
            'product_id' => $product->id,
            'SKU' => 'SKU-'.uniqid(),
            'low_stock_threshold' => 2,
            'price' => 5000.00,
            'stock' => 5,
            'is_primary' => 1,
            'weight' => 0,
            'dimensions' => '0x0x0',
            'variant_slug' => 'v-'.uniqid(),
        ]);

        return $product;
    }

    public function test_admin_can_create_region_and_location_and_see_dependencies(): void
    {
        $this->actingAs($this->admin());

        $this->post(route('admin.regions.store'), [
            'name' => 'Arusha Region',
            'country' => 'Tanzania',
            'sort_order' => 2,
            'is_active' => 1,
        ])->assertRedirect();

        $region = Region::where('name', 'Arusha Region')->first();
        $this->assertNotNull($region);
        $this->assertTrue($region->is_active);

        $this->post(route('admin.regions.locations.store', $region), [
            'name' => 'Arusha CBD',
            'shipping_fee' => 3000,
            'pickup_fee' => 1000,
            'pickup_eta' => '1 day',
            'door_eta' => '2 days',
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('locations', [
            'region_id' => $region->id,
            'name' => 'Arusha CBD',
            'shipping_fee' => 3000,
        ]);

        $this->get(route('admin.regions.index'))
            ->assertOk()
            ->assertSee('Arusha Region')
            ->assertSee('Arusha CBD')
            ->assertSee('locations')
            ->assertSee('orders');
    }

    public function test_product_page_lists_only_active_regions(): void
    {
        $this->region('LiveRegionXYZ', true);
        $this->region('DeadRegionXYZ', false);
        $product = $this->product();

        $this->get('/product/'.$product->slug)
            ->assertOk()
            ->assertSee('LiveRegionXYZ')
            ->assertDontSee('DeadRegionXYZ');
    }

    public function test_locations_api_returns_only_active_locations_of_active_region(): void
    {
        $region = $this->region('Mwanza Region');
        Location::create(['region_id' => $region->id, 'name' => 'LiveLocationXYZ', 'shipping_fee' => 3000, 'is_active' => true]);
        Location::create(['region_id' => $region->id, 'name' => 'DeadLocationXYZ', 'shipping_fee' => 3000, 'is_active' => false]);

        $this->getJson(route('api.regions.locations', $region->id))
            ->assertOk()
            ->assertJsonFragment(['name' => 'LiveLocationXYZ'])
            ->assertJsonMissing(['name' => 'DeadLocationXYZ']);
    }

    public function test_locations_api_rejects_inactive_region(): void
    {
        $region = $this->region('Dormant Region', false);
        Location::create(['region_id' => $region->id, 'name' => 'HiddenLocationXYZ', 'shipping_fee' => 3000, 'is_active' => true]);

        $this->getJson(route('api.regions.locations', $region->id))
            ->assertOk()
            ->assertExactJson(['locations' => []]);
    }

    public function test_delivery_quote_reports_method_availability(): void
    {
        $region = $this->region('Dodoma Region');
        Location::create([
            'region_id' => $region->id,
            'name' => 'Dodoma Town',
            'shipping_fee' => 4000,
            'pickup_fee' => null,
            'is_active' => true,
        ]);

        $this->getJson(route('api.delivery.quote', ['region' => $region->name, 'location' => 'Dodoma Town', 'subtotal' => 0]))
            ->assertOk()
            ->assertJsonPath('door.available', true)
            ->assertJsonPath('door.fee', 4000)
            ->assertJsonPath('pickup.available', false);

        $pickupRegion = $this->region('Mbeya Region');
        Location::create([
            'region_id' => $pickupRegion->id,
            'name' => 'Mbeya Station',
            'shipping_fee' => 5000,
            'pickup_fee' => 1500,
            'is_active' => true,
        ]);

        $this->getJson(route('api.delivery.quote', ['region' => $pickupRegion->name, 'location' => 'Mbeya Station', 'subtotal' => 0]))
            ->assertOk()
            ->assertJsonPath('pickup.available', true)
            ->assertJsonPath('pickup.fee', 1500);
    }

    public function test_deactivating_region_preserves_historical_order_address(): void
    {
        $region = $this->region('Tanga Region', true);

        $order = \App\Models\Order::create([
            'total_amount' => 10000,
            'status' => 'pending',
        ]);

        $address = ShippingAddress::create([
            'order_id' => $order->id,
            'customer_id' => null,
            'name' => 'Old Buyer',
            'phone' => '0700000000',
            'address' => '1 Old Street',
            'city' => 'Tanga',
            'state' => 'Tanga Region',
            'postal_code' => '21101',
            'country' => 'Tanzania',
        ]);

        $this->actingAs($this->admin())
            ->put(route('admin.regions.update', $region), [
                'name' => 'Tanga Region',
                'country' => 'Tanzania',
                'sort_order' => 0,
                'is_active' => '0',
            ])->assertRedirect();

        $this->assertFalse($region->fresh()->is_active);
        $this->assertSame('Tanga Region', $address->fresh()->state);
    }
}
