<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Region;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class RegionLocationAdminTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    public function test_regions_page_renders(): void
    {
        $this->actingAs($this->admin())->get(route('admin.regions.index'))->assertOk();
    }

    public function test_region_can_be_created(): void
    {
        $this->actingAs($this->admin())->post(route('admin.regions.store'), [
            'name' => 'Coastal',
            'country' => 'Tanzania',
            'sort_order' => 5,
            'is_active' => '1',
        ])->assertRedirect();

        $region = Region::where('name', 'Coastal')->firstOrFail();
        $this->assertTrue((bool) $region->is_active);
        $this->assertSame('Tanzania', $region->country);
    }

    public function test_region_can_be_updated(): void
    {
        $region = Region::create(['name' => 'Highlands', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.regions.update', $region), [
            'name' => 'Highlands North',
            'country' => 'Tanzania',
            'sort_order' => 9,
            'is_active' => '0',
        ])->assertRedirect();

        $region->refresh();
        $this->assertSame('Highlands North', $region->name);
        $this->assertSame(9, $region->sort_order);
        $this->assertFalse((bool) $region->is_active);
    }

    public function test_location_can_be_created_under_a_region(): void
    {
        $region = Region::create(['name' => 'Lake Zone', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.regions.locations.store', $region), [
            'name' => 'Moshi',
            'postal_code' => '23001',
            'shipping_fee' => 5000,
            'pickup_fee' => 2000,
            'free_over' => 100000,
            'eta_label' => '2-3 days',
            'pickup_eta' => '1 day',
            'door_eta' => '3 days',
            'sort_order' => 1,
            'is_active' => '1',
        ])->assertRedirect();

        $location = Location::where('name', 'Moshi')->firstOrFail();
        $this->assertSame($region->id, $location->region_id);
        $this->assertTrue((bool) $location->is_active);
    }

    public function test_location_can_be_updated(): void
    {
        $region = Region::create(['name' => 'Northern', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $location = $region->locations()->create([
            'name' => 'Arusha', 'shipping_fee' => 4000, 'is_active' => true, 'sort_order' => 1,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.locations.update', $location), [
            'name' => 'Arusha Town',
            'shipping_fee' => 6500,
            'is_active' => '0',
        ])->assertRedirect();

        $location->refresh();
        $this->assertSame('Arusha Town', $location->name);
        $this->assertFalse((bool) $location->is_active);
    }

    public function test_updating_a_location_preserves_fields_the_form_did_not_submit(): void
    {
        $region = Region::create(['name' => 'Coast', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $location = $region->locations()->create([
            'name' => 'Tanga',
            'postal_code' => '23401',
            'shipping_fee' => 3000,
            'pickup_fee' => 1500,
            'free_over' => 90000,
            'eta_label' => '2 days',
            'pickup_eta' => 'same day',
            'door_eta' => '3 days',
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $this->actingAs($this->admin())->put(route('admin.locations.update', $location), [
            'name' => 'Tanga Port',
        ])->assertRedirect();

        $location->refresh();

        $this->assertSame('Tanga Port', $location->name);
        $this->assertSame('23401', $location->postal_code);
        $this->assertSame('2 days', $location->eta_label);
        $this->assertSame('same day', $location->pickup_eta);
        $this->assertSame('3 days', $location->door_eta);
        $this->assertNotNull($location->free_over);
        $this->assertTrue((bool) $location->is_active);
    }

    public function test_region_update_preserves_fields_not_submitted(): void
    {
        $region = Region::create(['name' => 'Delta', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 4]);

        $this->actingAs($this->admin())->put(route('admin.regions.update', $region), [
            'name' => 'Delta Zone',
        ])->assertRedirect();

        $region->refresh();
        $this->assertSame('Delta Zone', $region->name);
        $this->assertSame('Tanzania', $region->country);
        $this->assertSame(4, $region->sort_order);
        $this->assertTrue((bool) $region->is_active);
    }

    public function test_unchecking_active_deactivates_region_and_location(): void
    {
        $region = Region::create(['name' => 'Coast', 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => 1]);
        $location = $region->locations()->create(['name' => 'Tanga', 'is_active' => true, 'sort_order' => 1]);
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.regions.update', $region), [
            'name' => 'Coast', 'country' => 'Tanzania', 'sort_order' => 1, 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse((bool) $region->refresh()->is_active);

        $this->actingAs($admin)->put(route('admin.locations.update', $location), [
            'name' => 'Tanga', 'is_active' => '0',
        ])->assertRedirect();
        $this->assertFalse((bool) $location->refresh()->is_active);
    }

    public function test_regions_page_lists_multiple_regions_and_their_locations(): void
    {
        foreach (['Delta', 'Highlands', 'Coastal'] as $index => $name) {
            $region = Region::create(['name' => $name, 'country' => 'Tanzania', 'is_active' => true, 'sort_order' => $index]);
            $region->locations()->create(['name' => $name.' City', 'shipping_fee' => 1000, 'is_active' => true, 'sort_order' => 1]);
        }

        $response = $this->actingAs($this->admin())->get(route('admin.regions.index'));

        $response->assertOk();
        $response->assertSee('Delta');
        $response->assertSee('Highlands');
        $response->assertSee('Coastal');
        $response->assertSee('Delta City');
    }
}
