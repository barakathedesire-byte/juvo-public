<?php

namespace Tests\Feature;

use App\Models\Shop;
use App\Models\Vendor;
use App\Models\VendorVerificationDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreProfileVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(): Vendor
    {
        return Vendor::where('status', 'active')->where('is_approved', 1)->firstOrFail();
    }

    public function test_vendor_can_update_store_profile(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor, 'vendor')->patch(route('vendor.settings.update'), [
            'name' => $vendor->name,
            'shop_name' => 'My Juvo Shop',
            'shop_description' => 'We sell gadgets.',
            'opening_hours' => 'Mon-Fri 9-6',
            'delivery_coverage' => 'Dar es Salaam',
            'latitude' => -6.8,
            'longitude' => 39.28,
        ])->assertRedirect(route('vendor.settings.index'));

        $vendor->refresh();
        $this->assertSame('Mon-Fri 9-6', $vendor->opening_hours['summary']);
        $this->assertSame('Dar es Salaam', $vendor->delivery_coverage['summary']);
        $this->assertEquals(-6.8, (float) $vendor->latitude);
    }

    public function test_vendor_cannot_submit_policy_fields(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor, 'vendor')->patch(route('vendor.settings.update'), [
            'name' => $vendor->name,
            'return_policy' => 'Vendor-defined returns',
            'shipping_policy' => 'Vendor-defined shipping',
            'privacy_policy' => 'Vendor privacy',
        ])->assertRedirect(route('vendor.settings.index'));

        $vendor->refresh();
        $this->assertArrayNotHasKey('return_policy', $vendor->getAttributes());
        $this->assertArrayNotHasKey('shipping_policy', $vendor->getAttributes());
        $this->assertArrayNotHasKey('privacy_policy', $vendor->getAttributes());
    }

    public function test_vendor_can_upload_a_verification_document_privately(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.settings.documents.store'), [
            'type' => 'business_license',
            'title' => 'Trading licence',
            'document' => UploadedFile::fake()->image('licence.jpg'),
        ])->assertRedirect();

        $document = VendorVerificationDocument::where('vendor_id', $vendor->id)->latest('id')->firstOrFail();
        $this->assertSame('pending', $document->status);
        $this->assertSame('local', $document->disk);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame('pending', $vendor->fresh()->verification_status);
    }

    public function test_vendor_cannot_delete_another_vendors_document(): void
    {
        Storage::fake('local');
        $vendor = $this->vendor();

        $rival = Vendor::create([
            'name' => 'Rival',
            'shop_name' => 'Rival Shop',
            'email' => 'rival-doc-'.uniqid().'@example.com',
            'password' => Hash::make('password'),
            'status' => 'active',
            'is_approved' => 1,
        ]);

        $document = VendorVerificationDocument::create([
            'vendor_id' => $rival->id,
            'type' => 'national_id',
            'file_path' => 'vendor_verification/'.$rival->id.'/id.jpg',
            'disk' => 'local',
            'status' => 'pending',
        ]);

        $this->actingAs($vendor, 'vendor')
            ->delete(route('vendor.settings.documents.destroy', $document->id))
            ->assertNotFound();

        $this->assertDatabaseHas('vendor_verification_documents', ['id' => $document->id]);
    }

    public function test_public_store_page_renders_for_active_vendor(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => 'Juvo Test Store', 'status' => 'active', 'slug' => 'juvo-test-store-'.$vendor->id]
        );

        $this->get(route('store.show', $shop->slug))
            ->assertOk()
            ->assertSee($shop->name);
    }

    public function test_public_store_page_is_hidden_for_suspended_vendor(): void
    {
        $vendor = $this->vendor();
        $shop = Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => 'Juvo Suspend Store', 'status' => 'active', 'slug' => 'juvo-suspend-store-'.$vendor->id]
        );

        $vendor->update(['status' => 'inactive']);

        $this->get(route('store.show', $shop->slug))->assertNotFound();
    }
}
