<?php

namespace Tests\Feature;

use App\Models\Rider\Rider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RiderProfileTest extends TestCase
{
    use DatabaseTransactions;

    protected function rider(): Rider
    {
        return Rider::create([
            'first_name' => 'Profile',
            'last_name' => 'Rider',
            'email' => 'profile-'.uniqid().'@example.com',
            'phone' => '072'.random_int(1000000, 9999999),
            'password' => 'password',
            'national_id' => 'NID-'.uniqid(),
            'date_of_birth' => '1995-01-01',
            'address' => '1 Rider Street',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'license_number' => 'LIC-'.uniqid(),
            'license_expiry' => now()->addYear()->toDateString(),
            'kyc_status' => 'approved',
            'is_active' => true,
            'background_check_status' => 'clear',
        ]);
    }

    public function test_profile_page_renders_real_rider_data(): void
    {
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->get(route('rider.profile'))
            ->assertOk()
            ->assertSee($rider->full_name)
            ->assertSee($rider->email)
            ->assertSee('Profile')
            ->assertSee('Verified');
    }

    public function test_profile_update_validates_and_saves_permitted_fields(): void
    {
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.update'), [
                'first_name' => 'Updated',
                'last_name' => 'Rider',
                'email' => $rider->email,
                'phone' => $rider->phone,
                'address' => 'New Address',
                'city' => 'Arusha',
                'state' => 'Arusha',
                'postal_code' => '23101',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertSame('Updated', $rider->first_name);
        $this->assertSame('Arusha', $rider->city);
    }

    public function test_profile_update_rejects_duplicate_email(): void
    {
        $rider = $this->rider();
        $other = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.update'), [
                'first_name' => $rider->first_name,
                'last_name' => $rider->last_name,
                'email' => $other->email,
                'phone' => $rider->phone,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_profile_photo_upload_stores_public_file(): void
    {
        Storage::fake('public');
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.photo'), [
                'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertNotNull($rider->profile_image);
        Storage::disk('public')->assertExists($rider->profile_image);
    }

    public function test_kyc_document_upload_stores_private_file(): void
    {
        Storage::fake('private');
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.documents'), [
                'document_type' => 'national_id_front',
                'document' => UploadedFile::fake()->create('id.pdf', 100, 'application/pdf'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertArrayHasKey('national_id_front', $rider->kyc_documents ?? []);
        Storage::disk('private')->assertExists($rider->kyc_documents['national_id_front']);
    }

    public function test_password_change_requires_current_password(): void
    {
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.password'), [
                'current_password' => 'wrong-password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.password'), [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPassword123!', $rider->password));
    }

    public function test_availability_toggle_updates_rider(): void
    {
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.profile.availability'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $rider->refresh();
        $this->assertTrue($rider->is_online);
    }

    public function test_logout_invalidates_session(): void
    {
        $rider = $this->rider();

        $this->actingAs($rider, 'rider')
            ->post(route('rider.logout'))
            ->assertRedirect(route('rider.login'));

        $this->assertGuest('rider');
    }
}
