<?php

namespace App\Services\Rules;

use App\Models\BusinessRule;
use Illuminate\Support\Facades\DB;

/**
 * Central business-rule registry.
 *
 * Every configurable rule that affects behaviour is declared here exactly
 * once with a stable key, a purpose, an owner, a validated schema, a safe
 * default, a failure behaviour and whether it is operator-editable. The
 * registry is the single source of truth and seeds the `business_rules`
 * table without ever clobbering an operator's saved value or active state.
 *
 * Hard safety/security controls must NOT live here: they are expressed in
 * code (see the automation action allowlist and the maker-checker engine).
 */
class RuleRegistry
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            /* ---------------- Advertising ---------------- */
            'ad_enabled' => [
                'name' => 'Advertising enabled',
                'description' => 'Master switch for the advertising module. When off no campaign is eligible.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'ad_auto_approve' => [
                'name' => 'Auto-approve compliant ads',
                'description' => 'Automatically approve paid campaigns that pass all eligibility rules without human review. Safe default is off.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'boolean',
                'default' => '0',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'ad_default_billing_model' => [
                'name' => 'Default billing model',
                'description' => 'Billing model applied to new ad campaigns when the vendor does not choose one.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'select',
                'default' => 'cpm',
                'rules' => 'in:cpm,cpc,flat',
                'options' => ['cpm' => 'Cost per mille (impressions)', 'cpc' => 'Cost per click', 'flat' => 'Flat rate'],
                'on_invalid' => 'default',
            ],
            'ad_max_active_per_vendor' => [
                'name' => 'Max active campaigns per vendor',
                'description' => 'Upper bound on concurrently active ad campaigns a single vendor may run.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'number',
                'default' => '5',
                'rules' => 'integer|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'ad_platform_take_rate' => [
                'name' => 'Platform take rate (%)',
                'description' => 'Percentage of ad spend retained by the platform.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'ad_min_budget' => [
                'name' => 'Minimum ad budget',
                'description' => 'Smallest paid budget a campaign must hold to be considered for delivery.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],
            'min_ad_budget' => [
                'name' => 'Minimum ad budget (legacy key)',
                'description' => 'Deprecated duplicate of ad_min_budget. Retained for existing reads; prefer ad_min_budget.',
                'category' => 'advertising',
                'owner' => 'Juvo Ads',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
                'deprecated' => true,
            ],

            /* ---------------- Pricing ---------------- */
            'pricing.max_discount_percent' => [
                'name' => 'Maximum discount percent',
                'description' => 'Cap on the discount percent a vendor may apply. 0 means no platform cap (margin guardrails still apply).',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'pricing.warn_discount_percent' => [
                'name' => 'Discount warning percent',
                'description' => 'Discount level at or above which a warning is raised to the vendor.',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'pricing.min_price' => [
                'name' => 'Minimum product price',
                'description' => 'Floor price below which a product may not be listed or discounted.',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],
            'pricing.min_margin_percent' => [
                'name' => 'Minimum margin percent',
                'description' => 'Minimum acceptable margin before a pricing action is flagged.',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'pricing.stack_mode' => [
                'name' => 'Promotion stacking mode',
                'description' => 'How multiple eligible promotions combine on a single line.',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'select',
                'default' => 'best_per_group',
                'rules' => 'in:best_per_group,best_single,stack',
                'options' => ['best_per_group' => 'Best offer per group', 'best_single' => 'Single best offer', 'stack' => 'Stack all eligible'],
                'on_invalid' => 'default',
            ],
            'max_discount_percent' => [
                'name' => 'Maximum discount percent (legacy key)',
                'description' => 'Deprecated duplicate of pricing.max_discount_percent. Retained for existing reads.',
                'category' => 'pricing',
                'owner' => 'Pricing',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
                'deprecated' => true,
            ],

            /* ---------------- Checkout ---------------- */
            'minimum_order_value' => [
                'name' => 'Minimum order value',
                'description' => 'Order subtotal a shopper must reach before checkout is allowed. 0 disables the minimum.',
                'category' => 'checkout',
                'owner' => 'Storefront',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],
            'free_shipping_threshold' => [
                'name' => 'Free shipping threshold',
                'description' => 'Order value at or above which shipping is free.',
                'category' => 'checkout',
                'owner' => 'Storefront',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],

            /* ---------------- Finance ---------------- */
            'default_commission_rate' => [
                'name' => 'Default vendor commission rate',
                'description' => 'Platform commission percentage applied to vendor sales when the vendor has no override.',
                'category' => 'finance',
                'owner' => 'Finance',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0|max:100',
                'on_invalid' => 'default',
            ],
            'minimum_payout' => [
                'name' => 'Minimum payout amount',
                'description' => 'Vendor balance a vendor must reach before a payout can be requested.',
                'category' => 'finance',
                'owner' => 'Finance',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],

            /* ---------------- Catalog ---------------- */
            'product_min_images' => [
                'name' => 'Minimum product images',
                'description' => 'Number of images a standard product submission must carry before it can be approved and published.',
                'category' => 'catalog',
                'owner' => 'Catalog',
                'type' => 'number',
                'default' => '3',
                'rules' => 'integer|min:1|max:20',
                'on_invalid' => 'default',
            ],

            /* ---------------- Inventory ---------------- */
            'low_stock_threshold' => [
                'name' => 'Low stock threshold',
                'description' => 'Stock level at or below which a product is flagged as low stock.',
                'category' => 'inventory',
                'owner' => 'Operations',
                'type' => 'number',
                'default' => '10',
                'rules' => 'integer|min:0',
                'on_invalid' => 'default',
            ],

            /* ---------------- Storefront ---------------- */
            'currency' => [
                'name' => 'Store currency',
                'description' => 'Default display currency code for the storefront.',
                'category' => 'storefront',
                'owner' => 'Storefront',
                'type' => 'select',
                'default' => 'TZS',
                'rules' => 'in:TZS,USD,UGX',
                'options' => ['TZS' => 'Tanzanian Shilling (TSh)', 'USD' => 'US Dollar ($)', 'UGX' => 'Ugandan Shilling (USh)'],
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: general ---------------- */
            'logistics.general.system_name' => [
                'name' => 'Logistics system name',
                'description' => 'Display name for the logistics portal and customer-facing tracking.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'text',
                'default' => 'Juvo Logistics',
                'rules' => 'string|max:120',
                'on_invalid' => 'default',
            ],
            'logistics.general.support_email' => [
                'name' => 'Support email',
                'description' => 'Primary support contact email for logistics operations.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'text',
                'default' => 'support@example.com',
                'rules' => 'string|max:190',
                'on_invalid' => 'default',
            ],
            'logistics.general.support_phone' => [
                'name' => 'Support phone',
                'description' => 'Primary support contact phone for logistics operations.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'text',
                'default' => '',
                'rules' => 'string|max:40',
                'on_invalid' => 'default',
            ],
            'logistics.general.timezone' => [
                'name' => 'Default operating timezone',
                'description' => 'Timezone used for logistics scheduling, reporting and notifications.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'select',
                'default' => 'Africa/Dar_es_Salaam',
                'rules' => 'in:Africa/Dar_es_Salaam,UTC,Europe/London',
                'options' => ['Africa/Dar_es_Salaam' => 'East Africa Time', 'UTC' => 'UTC', 'Europe/London' => 'London'],
                'on_invalid' => 'default',
            ],
            'logistics.general.maintenance_mode' => [
                'name' => 'Maintenance mode',
                'description' => 'Temporarily restrict logistics operations while maintenance is performed.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '0',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.general.service_available' => [
                'name' => 'Logistics service available',
                'description' => 'Master availability switch for logistics order acceptance.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: orders & delivery ---------------- */
            'logistics.orders.delivery_attempt_limit' => [
                'name' => 'Delivery attempt limit',
                'description' => 'Maximum delivery attempts before an order is returned or escalated.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '3',
                'rules' => 'integer|min:1|max:10',
                'on_invalid' => 'default',
            ],
            'logistics.orders.reattempt_delay_hours' => [
                'name' => 'Reattempt delay (hours)',
                'description' => 'Minimum delay before a failed delivery can be reattempted.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '24',
                'rules' => 'integer|min:0|max:168',
                'on_invalid' => 'default',
            ],
            'logistics.orders.proof_of_delivery_required' => [
                'name' => 'Proof of delivery required',
                'description' => 'Require proof of delivery evidence before completion.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.orders.pickup_confirmation_required' => [
                'name' => 'Pickup confirmation required',
                'description' => 'Require rider confirmation at pickup.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: dispatch & riders ---------------- */
            'logistics.dispatch.manual_dispatch_enabled' => [
                'name' => 'Manual dispatch enabled',
                'description' => 'Allow administrators to manually assign riders.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.dispatch.auto_dispatch_enabled' => [
                'name' => 'Automatic dispatch enabled',
                'description' => 'Enable the smart dispatch engine for eligible orders.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.dispatch.max_active_assignments_per_rider' => [
                'name' => 'Maximum active assignments per rider',
                'description' => 'Upper bound of concurrent assignments for a rider.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '3',
                'rules' => 'integer|min:1|max:20',
                'on_invalid' => 'default',
            ],
            'logistics.dispatch.assignment_acceptance_timeout_minutes' => [
                'name' => 'Assignment acceptance timeout (minutes)',
                'description' => 'How long a rider has to accept an assignment before reassignment.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '5',
                'rules' => 'integer|min:1|max:60',
                'on_invalid' => 'default',
            ],
            'logistics.dispatch.unassigned_alert_threshold_minutes' => [
                'name' => 'Unassigned order alert threshold (minutes)',
                'description' => 'Alert when an order remains unassigned beyond this threshold.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '15',
                'rules' => 'integer|min:1|max:240',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: pricing ---------------- */
            'logistics.pricing.minimum_delivery_fee' => [
                'name' => 'Minimum delivery fee',
                'description' => 'Minimum fee applied to delivery pricing.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],
            'logistics.pricing.free_delivery_threshold' => [
                'name' => 'Free delivery threshold',
                'description' => 'Order subtotal at or above which delivery becomes free.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '0',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: cash & COD ---------------- */
            'logistics.cod.enabled' => [
                'name' => 'COD available',
                'description' => 'Enable cash on delivery as a payment option.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.cod.submission_deadline_hours' => [
                'name' => 'Cash submission deadline (hours)',
                'description' => 'Deadline for riders to submit collected cash.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '24',
                'rules' => 'integer|min:1|max:168',
                'on_invalid' => 'default',
            ],
            'logistics.cod.discrepancy_threshold' => [
                'name' => 'COD discrepancy escalation threshold',
                'description' => 'Discrepancy amount that triggers escalation.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '5000',
                'rules' => 'numeric|min:0',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: notifications ---------------- */
            'logistics.notifications.quiet_hours_enabled' => [
                'name' => 'Quiet hours enabled',
                'description' => 'Enable quiet hours for non-critical notifications.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '0',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.notifications.retry_limit' => [
                'name' => 'Notification retry limit',
                'description' => 'Maximum retry attempts for failed notifications.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '3',
                'rules' => 'integer|min:0|max:10',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: routing & tracking ---------------- */
            'logistics.tracking.gps_freshness_minutes' => [
                'name' => 'GPS freshness threshold (minutes)',
                'description' => 'Age after which rider location is considered stale.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '5',
                'rules' => 'integer|min:1|max:60',
                'on_invalid' => 'default',
            ],
            'logistics.tracking.location_history_retention_days' => [
                'name' => 'Location history retention (days)',
                'description' => 'Retention period for rider location history.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '30',
                'rules' => 'integer|min:1|max:365',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: predictive & anomalies ---------------- */
            'logistics.predictive.delay_prediction_enabled' => [
                'name' => 'Delay prediction enabled',
                'description' => 'Enable delay prediction intelligence.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'boolean',
                'default' => '1',
                'rules' => 'boolean',
                'on_invalid' => 'default',
            ],
            'logistics.predictive.delay_risk_threshold' => [
                'name' => 'Delivery risk threshold',
                'description' => 'Risk score at which an order is flagged.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '70',
                'rules' => 'integer|min:0|max:100',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: support & disputes ---------------- */
            'logistics.support.resolution_deadline_hours' => [
                'name' => 'Support resolution deadline (hours)',
                'description' => 'Default resolution deadline for support tickets.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '48',
                'rules' => 'integer|min:1|max:720',
                'on_invalid' => 'default',
            ],

            /* ---------------- Logistics: data management ---------------- */
            'logistics.data.delivery_history_retention_days' => [
                'name' => 'Delivery history retention (days)',
                'description' => 'Retention period for delivery history.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '365',
                'rules' => 'integer|min:30|max:3650',
                'on_invalid' => 'default',
            ],
            'logistics.data.gps_history_retention_days' => [
                'name' => 'GPS history retention (days)',
                'description' => 'Retention period for GPS history.',
                'category' => 'logistics',
                'owner' => 'Juvo Logistics',
                'type' => 'number',
                'default' => '90',
                'rules' => 'integer|min:1|max:3650',
                'on_invalid' => 'default',
            ],
        ];
    }

    public function definition(string $key): ?array
    {
        return $this->definitions()[$key] ?? null;
    }

    public function isManaged(string $key): bool
    {
        return $this->definition($key) !== null;
    }

    /**
     * @return array<int, string>
     */
    public function optionValues(string $key): array
    {
        $options = $this->definition($key)['options'] ?? [];

        return array_map('strval', array_keys($options));
    }

    /**
     * Seed/refresh governance metadata for every declared rule. Operator
     * values and active state on existing rows are never overwritten.
     */
    public function sync(): int
    {
        $count = 0;

        DB::transaction(function () use (&$count) {
            foreach ($this->definitions() as $key => $def) {
                $rule = BusinessRule::firstOrNew(['key' => $key]);

                $rule->fill([
                    'name' => $def['name'],
                    'description' => $def['description'] ?? null,
                    'category' => $def['category'] ?? null,
                    'owner' => $def['owner'] ?? null,
                    'value_type' => $def['type'] ?? 'text',
                    'validation_rules' => $def['rules'] ?? null,
                    'default_value' => $def['default'] ?? null,
                    'on_invalid' => $def['on_invalid'] ?? 'default',
                    'is_managed' => true,
                    'is_editable' => $def['editable'] ?? true,
                ]);

                if (array_key_exists('options', $def)) {
                    $rule->options = json_encode($def['options']);
                }

                if (! $rule->exists) {
                    $rule->value = $def['default'] ?? '';
                    $rule->is_active = true;
                    $rule->version = 1;
                }

                $rule->save();

                if ($rule->revisions()->where('version', $rule->version)->doesntExist()) {
                    $rule->revisions()->create([
                        'key' => $rule->key,
                        'version' => $rule->version,
                        'value' => $rule->value,
                        'is_active' => $rule->is_active,
                        'effective_from' => $rule->effective_from,
                        'effective_to' => $rule->effective_to,
                        'changed_by' => null,
                        'reason' => 'Registered from definition.',
                        'snapshot' => $rule->only(['key', 'value', 'value_type', 'is_active', 'version']),
                    ]);
                }

                $count++;
            }
        });

        app(RuleEngine::class)->flush();

        return $count;
    }
}
