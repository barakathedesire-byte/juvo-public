<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            // SiteSettingsSeeder::class,
            RolesAndPermissionsSeeder::class,
            StaffAccessSeeder::class,
            AdminSeeder::class,
            LanguageSeeder::class,
            PaymentGatewaySeeder::class,
            PaymentSeeder::class,
            RefundSeeder::class,

            // Juvo demo storefront (idempotent). JuvoCatalogSeeder needs an
            // existing vendor/shop and will warn + skip if none exists.
            JuvoPlatformSeeder::class,
            JuvoCatalogSeeder::class,
            OrderSeeder::class,
            JuvoHomepageSeeder::class,
            JuvoPagesSeeder::class,
            HrSeeder::class,

            // Populates the Algorithm Engine, anomaly rules and default
            // automation workflows. Without this a fresh install shows an
            // empty Algorithm Engine page (nothing else runs intelligence:sync).
            IntelligenceSeeder::class,

            // Public demo access: every account gets the demo password and
            // admin@example.com becomes the shared demo account for all portals.
            DemoAccessSeeder::class,
        ]);
    }
}
