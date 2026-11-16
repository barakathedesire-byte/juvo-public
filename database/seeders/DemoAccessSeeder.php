<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Logistics\LogisticsAdmin;
use App\Models\Rider\Rider;
use App\Models\SalesRep;
use App\Models\Shop;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

class DemoAccessSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('demo.password', 'admin');
        $hash = Hash::make($password);
        $email = (string) config('demo.email', 'admin@example.com');

        // Reset every existing account password in every authentication table.
        foreach (['users', 'vendors', 'customers', 'logistics_admins', 'riders'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'password')) {
                $update = ['password' => $hash];

                if (Schema::hasColumn($table, 'remember_token')) {
                    $update['remember_token'] = null;
                }

                DB::table($table)->update($update);
            }
        }

        $admin = User::firstOrNew(['email' => $email]);
        $admin->forceFill([
            'name' => 'Demo Admin',
            'password' => $password,
            'role' => 'admin',
            'account_status' => 'active',
            'portal_access' => true,
            'portal_approved_at' => now(),
            'email_verified_at' => now(),
        ])->save();

        Role::findOrCreate('super_admin', 'web');
        $admin->syncRoles(['super_admin']);

        $vendor = Vendor::firstOrNew(['email' => $email]);
        $vendor->forceFill([
            'name' => 'Demo Vendor',
            'shop_name' => 'Demo Shop',
            'password' => $password,
            'phone' => '0700000001',
            'status' => 'active',
            'is_approved' => true,
            'email_verified_at' => now(),
            'verification_status' => 'verified',
            'verified_at' => now(),
        ])->save();

        Shop::firstOrCreate(
            ['vendor_id' => $vendor->id],
            ['name' => 'Demo Shop', 'slug' => 'demo-shop', 'status' => 'active']
        );

        $customer = Customer::firstOrNew(['email' => $email]);
        $customer->forceFill([
            'name' => 'Demo Customer',
            'password' => $password,
            'phone' => '0700000002',
            'national_id' => 'DEMO-CUSTOMER-0001',
            'address' => 'Demo Street',
            'next_of_kin_name' => 'Demo Next of Kin',
            'next_of_kin_phone' => '0700000003',
            'status' => 'active',
            'email_verified_at' => now(),
        ])->save();

        $logistics = LogisticsAdmin::firstOrNew(['email' => $email]);
        $logistics->forceFill([
            'name' => 'Demo Logistics Admin',
            'password' => $password,
            'phone' => '0700000004',
            'role' => 'super_admin',
            'is_active' => true,
            'account_status' => 'active',
            'portal_access' => true,
            'portal_approved_at' => now(),
            'auth_version' => 0,
        ])->save();

        $rider = Rider::firstOrNew(['email' => $email]);
        $rider->forceFill([
            'first_name' => 'Demo',
            'last_name' => 'Rider',
            'password' => $password,
            'phone' => '0700000005',
            'national_id' => 'DEMO-RIDER-0001',
            'date_of_birth' => '1990-01-01',
            'gender' => 'other',
            'address' => 'Demo Street',
            'city' => 'Dar es Salaam',
            'state' => 'Dar es Salaam',
            'vehicle_type' => 'motorcycle',
            'license_plate' => 'DEMO-0001',
            'license_number' => 'DEMO-LIC-0001',
            'license_expiry' => now()->addYears(5)->toDateString(),
            'kyc_status' => 'approved',
            'kyc_reviewed_at' => now(),
            'is_active' => true,
            'background_check_status' => 'clear',
            'background_check_completed_at' => now(),
        ])->save();

        SalesRep::updateOrCreate(
            ['email' => $email],
            [
                'user_id' => $admin->id,
                'code' => 'ADMIN',
                'name' => 'Demo Admin',
                'phone' => '0700000006',
                'national_id' => 'DEMO-AGENT-0001',
                'address' => 'Demo Street',
                'next_of_kin_name' => 'Demo Next of Kin',
                'next_of_kin_phone' => '0700000003',
                'commission_rate' => 5,
                'monthly_target' => 0,
                'is_active' => true,
                'status' => 'active',
                'verification_status' => 'verified',
                'terms_version' => 'demo',
                'terms_accepted_at' => now(),
                'approved_at' => now(),
                'currency' => 'TZS',
            ]
        );
    }
}
