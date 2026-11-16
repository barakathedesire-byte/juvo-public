<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorStaff;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class VendorStaffTest extends TestCase
{
    use DatabaseTransactions;

    protected function vendor(array $attrs = []): Vendor
    {
        return Vendor::create([
            'name' => $attrs['name'] ?? 'Owner',
            'shop_name' => $attrs['shop_name'] ?? 'Owner Shop',
            'email' => $attrs['email'] ?? uniqid('own').'@example.com',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
        ]);
    }

    public function test_vendor_can_create_a_staff_account(): void
    {
        $vendor = $this->vendor();

        $this->actingAs($vendor, 'vendor')->post(route('vendor.staff.store'), [
            'name' => 'Assistant',
            'email' => 'assistant@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'manager',
        ])->assertRedirect(route('vendor.staff.index'));

        $staff = VendorStaff::where('email', 'assistant@example.com')->firstOrFail();
        $this->assertSame($vendor->id, $staff->vendor_id);
        $this->assertSame('manager', $staff->role);
        $this->assertTrue(Hash::check('secret123', $staff->password));
    }

    public function test_duplicate_staff_email_is_rejected(): void
    {
        $vendor = $this->vendor();
        VendorStaff::create([
            'vendor_id' => $vendor->id,
            'name' => 'Existing',
            'email' => 'dupe@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->actingAs($vendor, 'vendor')->post(route('vendor.staff.store'), [
            'name' => 'Another',
            'email' => 'dupe@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'staff',
        ])->assertSessionHasErrors('email');
    }

    public function test_vendor_can_deactivate_and_promote_staff(): void
    {
        $vendor = $this->vendor();
        $staff = VendorStaff::create([
            'vendor_id' => $vendor->id,
            'name' => 'Worker',
            'email' => 'worker@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->actingAs($vendor, 'vendor')->put(route('vendor.staff.update', $staff->id), [
            'name' => 'Worker',
            'role' => 'manager',
            'status' => 'inactive',
        ])->assertRedirect();

        $staff->refresh();
        $this->assertSame('manager', $staff->role);
        $this->assertSame('inactive', $staff->status);
    }

    public function test_owner_account_cannot_be_deleted(): void
    {
        $vendor = $this->vendor();
        $owner = VendorStaff::create([
            'vendor_id' => $vendor->id,
            'name' => 'Owner',
            'email' => 'ownerstaff@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'owner',
            'status' => 'active',
        ]);

        $this->actingAs($vendor, 'vendor')->delete(route('vendor.staff.destroy', $owner->id));
        $this->assertDatabaseHas('vendor_staff', ['id' => $owner->id]);
    }

    public function test_vendor_cannot_manage_another_vendors_staff(): void
    {
        $vendorA = $this->vendor();
        $vendorB = $this->vendor();
        $staff = VendorStaff::create([
            'vendor_id' => $vendorB->id,
            'name' => 'B Staff',
            'email' => 'bstaff@example.com',
            'password' => Hash::make('secret123'),
            'role' => 'staff',
            'status' => 'active',
        ]);

        $this->actingAs($vendorA, 'vendor')
            ->put(route('vendor.staff.update', $staff->id), ['name' => 'Hacked', 'role' => 'owner', 'status' => 'active'])
            ->assertNotFound();

        $this->actingAs($vendorA, 'vendor')
            ->delete(route('vendor.staff.destroy', $staff->id))
            ->assertNotFound();
    }
}
