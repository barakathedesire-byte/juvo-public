<?php

namespace Database\Seeders;

use App\Models\BusinessRule;
use App\Models\Currency;
use App\Models\Region;
use App\Models\StoreSetting;
use App\Models\Vendor;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class JuvoPlatformSeeder extends Seeder
{
    public function run(): void
    {
        Vendor::firstOrCreate(
            ['email' => 'seller@example.com'],
            [
                'name' => 'Juvo Demo Vendor',
                'password' => Hash::make('admin'),
                'phone' => '+255700000000',
                'status' => 'active',
                'is_approved' => true,
            ]
        );

        Currency::updateOrCreate(
            ['code' => 'TZS'],
            ['name' => 'Tanzanian Shilling', 'symbol' => 'TSh', 'exchange_rate' => 1.0000]
        );

        Currency::where('code', 'TZS')->whereNull('exchange_rate')->update(['exchange_rate' => 1.0]);

        StoreSetting::updateOrCreate(['key' => 'default_currency'], ['value' => 'TZS']);

        $regions = [
            'Arusha', 'Dar es Salaam', 'Dodoma', 'Geita', 'Iringa', 'Kagera', 'Katavi',
            'Kigoma', 'Kilimanjaro', 'Lindi', 'Manyara', 'Mara', 'Mbeya', 'Morogoro',
            'Mtwara', 'Mwanza', 'Njombe', 'Pemba North', 'Pemba South', 'Pwani',
            'Rukwa', 'Ruvuma', 'Shinyanga', 'Simiyu', 'Singida', 'Songwe', 'Tabora',
            'Tanga', 'Unguja North', 'Unguja South', 'Zanzibar Urban West',
        ];

        foreach ($regions as $name) {
            Region::firstOrCreate(['name' => $name], ['country' => 'Tanzania']);
        }

        $rules = [
            [
                'key' => 'minimum_order_value',
                'name' => 'Minimum order value',
                'description' => 'Reject checkout when the cart total is below this value.',
                'value_type' => 'number',
                'value' => '20000',
            ],
            [
                'key' => 'free_shipping_threshold',
                'name' => 'Free shipping threshold',
                'description' => 'Orders at or above this value get free shipping.',
                'value_type' => 'number',
                'value' => '100000',
            ],
            [
                'key' => 'ad_min_budget',
                'name' => 'Minimum ad budget',
                'description' => 'Ad campaigns must be paid with a budget at or above this amount to be considered.',
                'value_type' => 'number',
                'value' => '50000',
            ],
            [
                'key' => 'ad_auto_approve',
                'name' => 'Auto-approve compliant ads',
                'description' => 'Automatically approve ad campaigns that pass all rules (paid, budget met, active product, verified vendor). Off by default; opt in explicitly.',
                'value_type' => 'boolean',
                'value' => '0',
            ],
            [
                'key' => 'max_discount_percent',
                'name' => 'Maximum discount percent',
                'description' => 'Cap the discount % a vendor is allowed to apply on their products.',
                'value_type' => 'number',
                'value' => '80',
            ],
            [
                'key' => 'low_stock_threshold',
                'name' => 'Low stock threshold',
                'description' => 'Items with stock below this threshold are flagged as low stock.',
                'value_type' => 'number',
                'value' => '10',
            ],
            [
                'key' => 'currency',
                'name' => 'Store currency',
                'description' => 'Default display currency code.',
                'value_type' => 'select',
                'value' => 'TZS',
                'options' => json_encode(['TZS' => 'Tanzanian Shilling (TSh)', 'USD' => 'US Dollar ($)', 'UGX' => 'Ugandan Shilling (USh)']),
            ],
        ];

        foreach ($rules as $rule) {
            BusinessRule::updateOrCreate(
                ['key' => $rule['key']],
                array_merge($rule, ['description' => $rule['description'] ?? null])
            );
        }

        $workflows = [
            [
                'name' => 'Vendor onboarding approval',
                'type' => 'approval',
                'trigger' => 'vendor_signup',
                'description' => 'A newly registered vendor stays pending until an admin reviews their documents and approves the account.',
                'config' => ['auto_approve' => false, 'require_documents' => true],
            ],
            [
                'name' => 'Ad campaign approval',
                'type' => 'approval',
                'trigger' => 'ad_submit',
                'description' => 'Ad campaigns are reviewed against business rules (payment, budget, product, vendor verification) and auto-approved when compliant.',
                'config' => ['auto_approve_from_rule' => 'ad_auto_approve', 'min_budget_rule' => 'ad_min_budget'],
            ],
            [
                'name' => 'Order ready for pickup',
                'type' => 'order',
                'trigger' => 'order_status_change',
                'description' => 'When an order is marked ready for pickup the shipment is pushed to the delivery partner.',
                'config' => ['push_fleetbase' => true],
            ],
            [
                'name' => 'Low stock alert',
                'type' => 'inventory',
                'trigger' => 'stock_update',
                'description' => 'Flag products that drop below the low stock threshold.',
                'config' => ['threshold_rule' => 'low_stock_threshold'],
            ],
        ];

        foreach ($workflows as $wf) {
            Workflow::firstOrCreate(
                ['name' => $wf['name']],
                $wf
            );
        }
    }
}
