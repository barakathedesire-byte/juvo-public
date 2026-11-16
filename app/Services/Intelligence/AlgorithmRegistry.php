<?php

namespace App\Services\Intelligence;

use App\Models\Algorithm;
use App\Models\AlgorithmVersion;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Central algorithm registry (Algorithm Engine).
 *
 * Every algorithm the platform runs is declared here exactly once. The
 * registry is the single source of truth for metadata, the config schema,
 * defaults and whether a real implementation actually exists. It seeds the
 * `algorithms` table without ever clobbering an operator's saved config or
 * activation state.
 */
class AlgorithmRegistry
{
    public const CACHE_KEY = 'algorithms.registry';

    /**
     * @return array<string, array<string, mixed>>
     */
    public function definitions(): array
    {
        return [
            'product_discovery' => [
                'name' => 'Product Discovery & Recommendation Engine',
                'category' => 'discovery',
                'owner' => 'Marketplace Intelligence',
                'icon' => 'fa-wand-magic-sparkles',
                'implemented' => true,
                'description' => 'Ranks eligible, in-stock products for every storefront discovery surface and personalises them per shopper while keeping paid placements strictly separate.',
                'config_schema' => [
                    'defaults' => [
                        'default_limit' => 12,
                        'max_limit' => 24,
                        'cache_ttl' => 300,
                        'diversity_max_per_vendor' => 3,
                        'personalisation' => true,
                        'weights' => [
                            'association' => 0.35,
                            'popularity' => 0.25,
                            'rating' => 0.15,
                            'freshness' => 0.15,
                            'affinity' => 0.10,
                        ],
                    ],
                    'fields' => [
                        'default_limit' => ['label' => 'Default items per surface', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:48'],
                        'max_limit' => ['label' => 'Maximum items per surface', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:96'],
                        'cache_ttl' => ['label' => 'Cache TTL (seconds)', 'type' => 'integer', 'rules' => 'required|integer|min:0|max:3600'],
                        'diversity_max_per_vendor' => ['label' => 'Max items per vendor', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:24'],
                        'personalisation' => ['label' => 'Enable personalisation', 'type' => 'boolean', 'rules' => 'boolean'],
                    ],
                ],
            ],
            'paid_ads' => [
                'name' => 'Paid Advertising Auction',
                'category' => 'monetisation',
                'owner' => 'Juvo Ads',
                'icon' => 'fa-bullhorn',
                'implemented' => true,
                'description' => 'The Juvo Ads auction (eCPM ranking, pacing, budget safety and attribution). Managed as a distinct module and surfaced to the storefront separately from organic discovery.',
                'config_schema' => [
                    'defaults' => ['enabled_on_discovery' => false, 'max_sponsored_per_surface' => 1],
                    'fields' => [
                        'enabled_on_discovery' => ['label' => 'Allow sponsored slots on discovery surfaces', 'type' => 'boolean', 'rules' => 'boolean'],
                        'max_sponsored_per_surface' => ['label' => 'Max sponsored slots per surface', 'type' => 'integer', 'rules' => 'integer|min:0|max:5'],
                    ],
                ],
            ],
            'search_ranking' => [
                'name' => 'Search & Ranking Engine',
                'category' => 'discovery',
                'owner' => 'Search',
                'icon' => 'fa-magnifying-glass',
                'implemented' => true,
                'description' => 'Catalog search: eligibility-first retrieval, deterministic weighted relevance ranking, spell/synonym handling, faceting and monetised placement — kept strictly separate from organic ranking.',
                'config_schema' => [
                    'defaults' => [
                        'per_page' => 12,
                        'max_per_page' => 48,
                        'candidate_limit' => 300,
                        'autocomplete_limit' => 8,
                        'require_in_stock' => true,
                        'diversity_max_per_vendor' => 4,
                        'max_sponsored' => 2,
                        'typo_tolerance' => true,
                        'cache_ttl' => 120,
                        'weights' => [
                            'exact_title' => 0.20,
                            'phrase_title' => 0.10,
                            'token_title' => 0.12,
                            'token_keywords' => 0.10,
                            'identifier' => 0.15,
                            'brand' => 0.06,
                            'category' => 0.05,
                            'availability' => 0.05,
                            'promotion' => 0.04,
                            'rating' => 0.05,
                            'popularity' => 0.05,
                            'freshness' => 0.03,
                            'engagement' => 0.05,
                        ],
                    ],
                    'fields' => [
                        'per_page' => ['label' => 'Results per page', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:96'],
                        'max_per_page' => ['label' => 'Maximum page size', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:120'],
                        'candidate_limit' => ['label' => 'Max candidates retrieved', 'type' => 'integer', 'rules' => 'required|integer|min:20|max:1000'],
                        'autocomplete_limit' => ['label' => 'Autocomplete suggestions', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:20'],
                        'require_in_stock' => ['label' => 'Only show purchasable products', 'type' => 'boolean', 'rules' => 'boolean'],
                        'diversity_max_per_vendor' => ['label' => 'Max results per vendor', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:24'],
                        'max_sponsored' => ['label' => 'Max sponsored placements', 'type' => 'integer', 'rules' => 'required|integer|min:0|max:6'],
                        'typo_tolerance' => ['label' => 'Enable typo tolerance', 'type' => 'boolean', 'rules' => 'boolean'],
                        'cache_ttl' => ['label' => 'Cache TTL (seconds)', 'type' => 'integer', 'rules' => 'required|integer|min:0|max:3600'],
                        'weights' => ['label' => 'Ranking signal weights', 'type' => 'json', 'rules' => 'nullable|array'],
                    ],
                ],
            ],
            'pricing_optimisation' => [
                'name' => 'Pricing & Promotion Optimisation',
                'category' => 'pricing',
                'owner' => 'Commercial',
                'icon' => 'fa-tags',
                'implemented' => true,
                'description' => 'Deterministic, funding-aware price and promotion evaluation: a single authoritative pricing calculation, guarded discount stacking, funded budgets, margin guardrails and explainable recommendations. Never changes vendor prices on its own.',
                'config_schema' => [
                    'defaults' => [
                        'stack_mode' => 'best_per_group',
                        'max_discount_percent' => 60,
                        'warn_discount_percent' => 40,
                        'min_margin_percent' => 0,
                        'min_price' => 0,
                        'auto_recommendations' => true,
                    ],
                    'fields' => [
                        'stack_mode' => ['label' => 'Discount stacking', 'type' => 'select', 'rules' => 'required|in:best_per_group,sum'],
                        'max_discount_percent' => ['label' => 'Maximum discount %', 'type' => 'integer', 'rules' => 'required|numeric|min:0|max:100'],
                        'warn_discount_percent' => ['label' => 'Warning discount %', 'type' => 'integer', 'rules' => 'required|numeric|min:0|max:100'],
                        'min_margin_percent' => ['label' => 'Minimum margin %', 'type' => 'integer', 'rules' => 'nullable|numeric|min:0|max:100'],
                        'min_price' => ['label' => 'Absolute price floor', 'type' => 'integer', 'rules' => 'nullable|numeric|min:0'],
                        'auto_recommendations' => ['label' => 'Surface recommendations', 'type' => 'boolean', 'rules' => 'boolean'],
                    ],
                ],
            ],
            'demand_forecasting' => [
                'name' => 'Demand Forecasting',
                'category' => 'planning',
                'owner' => 'Operations',
                'icon' => 'fa-chart-line',
                'implemented' => true,
                'description' => 'SKU, category and catalog demand forecasting from order history: a least-squares trend modulated by a day-of-week seasonality index, with confidence scored on observed volume.',
                'config_schema' => [
                    'defaults' => [
                        'lookback_days' => 60,
                        'days_ahead' => 7,
                        'min_history_days' => 7,
                        'seasonality' => true,
                        'safety_stock_percent' => 10,
                    ],
                    'fields' => [
                        'lookback_days' => ['label' => 'History window (days)', 'type' => 'integer', 'rules' => 'required|integer|min:7|max:365'],
                        'days_ahead' => ['label' => 'Forecast horizon (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:90'],
                        'min_history_days' => ['label' => 'Days with sales needed for full confidence', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:90'],
                        'seasonality' => ['label' => 'Apply day-of-week seasonality', 'type' => 'boolean', 'rules' => 'boolean'],
                        'safety_stock_percent' => ['label' => 'Safety stock %', 'type' => 'integer', 'rules' => 'required|numeric|min:0|max:100'],
                    ],
                ],
            ],
            'fraud_detection' => [
                'name' => 'Fraud & Risk Detection',
                'category' => 'trust',
                'owner' => 'Risk',
                'icon' => 'fa-shield-halved',
                'implemented' => true,
                'description' => 'Explainable order risk scoring across six signals — order value, ordering velocity, account age, payment state, refund history and guest checkout — returning a 0–100 score and a review band. Never blocks an order on its own.',
                'config_schema' => [
                    'defaults' => [
                        'high_value_threshold' => 0,
                        'velocity_window_orders' => 3,
                        'new_account_days' => 7,
                        'refund_history_limit' => 1,
                        'medium_threshold' => 30,
                        'high_threshold' => 60,
                        'critical_threshold' => 85,
                    ],
                    'fields' => [
                        'high_value_threshold' => ['label' => 'High-value threshold (0 = derive from 95th percentile)', 'type' => 'integer', 'rules' => 'required|numeric|min:0'],
                        'velocity_window_orders' => ['label' => 'Orders in 24h that count as velocity', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:50'],
                        'new_account_days' => ['label' => 'Account age treated as new (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:365'],
                        'refund_history_limit' => ['label' => 'Prior refunds that trigger the signal', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:20'],
                        'medium_threshold' => ['label' => 'Medium band starts at', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:100'],
                        'high_threshold' => ['label' => 'High band starts at', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:100'],
                        'critical_threshold' => ['label' => 'Critical band starts at', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:100'],
                    ],
                ],
            ],
            'retention' => [
                'name' => 'Customer Retention & Churn',
                'category' => 'growth',
                'owner' => 'Growth',
                'icon' => 'fa-user-group',
                'implemented' => true,
                'description' => 'RFM-based churn propensity and projected lifetime value. Recency is measured against each customer\'s own observed ordering cycle rather than a blanket clock, producing a segment alongside the probability.',
                'config_schema' => [
                    'defaults' => [
                        'window_days' => 365,
                        'default_cycle_days' => 30,
                        'dormant_days' => 90,
                        'new_customer_days' => 30,
                        'loyal_min_orders' => 5,
                        'clv_horizon_days' => 365,
                    ],
                    'fields' => [
                        'window_days' => ['label' => 'RFM analysis window (days)', 'type' => 'integer', 'rules' => 'required|integer|min:30|max:1095'],
                        'default_cycle_days' => ['label' => 'Fallback ordering cycle (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:365'],
                        'dormant_days' => ['label' => 'Recency that counts as dormant', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:730'],
                        'new_customer_days' => ['label' => 'Account age treated as new (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:365'],
                        'loyal_min_orders' => ['label' => 'Orders needed to be loyal', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:100'],
                        'clv_horizon_days' => ['label' => 'CLV projection horizon (days)', 'type' => 'integer', 'rules' => 'required|integer|min:30|max:1095'],
                    ],
                ],
            ],
            'vendor_performance' => [
                'name' => 'Vendor Performance Scoring',
                'category' => 'marketplace',
                'owner' => 'Marketplace Operations',
                'icon' => 'fa-store',
                'implemented' => true,
                'description' => 'Vendor scoring over fulfilment, on-time delivery, product quality, cancellation rate and support load, with a confidence term that makes explicit how many orders the grade rests on.',
                'config_schema' => [
                    'defaults' => [
                        'window_days' => 90,
                        'on_time_days' => 7,
                        'confidence_order_count' => 20,
                    ],
                    'fields' => [
                        'window_days' => ['label' => 'Analysis window (days)', 'type' => 'integer', 'rules' => 'required|integer|min:7|max:365'],
                        'on_time_days' => ['label' => 'Days allowed to count as on time', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:60'],
                        'confidence_order_count' => ['label' => 'Orders needed for full confidence', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:500'],
                    ],
                ],
            ],
            'order_routing' => [
                'name' => 'Order Routing & Fulfilment',
                'category' => 'fulfilment',
                'owner' => 'Operations',
                'icon' => 'fa-route',
                'implemented' => true,
                'description' => 'Ranks the fulfilment paths actually available for an order — vendor self-fulfilment, the delivery partner, flat-rate shipping — on cost, reliability and coverage, and names the best one with its reasons.',
                'config_schema' => [
                    'defaults' => ['cost_baseline' => 5.0],
                    'fields' => [
                        'cost_baseline' => ['label' => 'Baseline delivery cost for scoring', 'type' => 'integer', 'rules' => 'required|numeric|min:0'],
                    ],
                ],
            ],
            'support_assist' => [
                'name' => 'Support Triage & Knowledge',
                'category' => 'support',
                'owner' => 'Support',
                'icon' => 'fa-headset',
                'implemented' => true,
                'description' => 'Ticket triage: urgency scoring, SLA risk measured against each ticket\'s own due dates, a suggested priority and queue, plus canned-response and published-page suggestions for the reply.',
                'config_schema' => [
                    'defaults' => [
                        'age_weight_hours' => 48,
                        'high_value_order_threshold' => 500,
                    ],
                    'fields' => [
                        'age_weight_hours' => ['label' => 'Open hours before age scores', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:720'],
                        'high_value_order_threshold' => ['label' => 'Linked order value that scores', 'type' => 'integer', 'rules' => 'required|numeric|min:0'],
                    ],
                ],
            ],
            'finance_intelligence' => [
                'name' => 'Finance Intelligence',
                'category' => 'finance',
                'owner' => 'Finance',
                'icon' => 'fa-coins',
                'implemented' => true,
                'description' => 'Ledger, payout and margin analytics over a rolling window, including payout ageing and revenue concentration reported as a Herfindahl-Hirschman index.',
                'config_schema' => [
                    'defaults' => [
                        'window_days' => 30,
                        'payout_ageing_days' => 14,
                        'min_margin_percent' => 0,
                        'concentration_threshold' => 0.40,
                    ],
                    'fields' => [
                        'window_days' => ['label' => 'Reporting window (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:365'],
                        'payout_ageing_days' => ['label' => 'Days before a payout is overdue', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:90'],
                        'min_margin_percent' => ['label' => 'Minimum platform margin %', 'type' => 'integer', 'rules' => 'required|numeric|min:0|max:100'],
                        'concentration_threshold' => ['label' => 'Max single-vendor revenue share', 'type' => 'integer', 'rules' => 'required|numeric|min:0|max:1'],
                    ],
                ],
            ],
            'agent_commission' => [
                'name' => 'Agent Commission Engine',
                'category' => 'finance',
                'owner' => 'Sales',
                'icon' => 'fa-people-group',
                'implemented' => true,
                'description' => 'Operational tuning for the sales-agent commission ledger. Commission rates and eligibility are governed by versioned commission policies, never by this algorithm; it only controls payout floors and reconciliation windows.',
                'config_schema' => [
                    'defaults' => [
                        'min_payout' => 0,
                        'reconcile_window_days' => 30,
                        'max_team_depth' => 1,
                    ],
                    'fields' => [
                        'min_payout' => ['label' => 'Minimum payout amount', 'type' => 'integer', 'rules' => 'required|numeric|min:0'],
                        'reconcile_window_days' => ['label' => 'Reconcile window (days)', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:365'],
                        'max_team_depth' => ['label' => 'Maximum team depth', 'type' => 'integer', 'rules' => 'required|integer|min:0|max:5'],
                    ],
                ],
            ],
            'agent_fraud_scoring' => [
                'name' => 'Agent Fraud Scoring',
                'category' => 'risk',
                'owner' => 'Sales',
                'icon' => 'fa-shield-halved',
                'implemented' => true,
                'description' => 'Explainable, rule-based abuse detection for the agent network. Thresholds are editable but detection stays deterministic — no opaque model ever decides whether commission is payable.',
                'config_schema' => [
                    'defaults' => [
                        'min_orders_for_rate' => 5,
                        'cancellation_rate_threshold' => 0.5,
                        'velocity_orders_per_day' => 10,
                        'auto_hold_severity' => 'high',
                    ],
                    'fields' => [
                        'min_orders_for_rate' => ['label' => 'Min orders before rate checks', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:100'],
                        'cancellation_rate_threshold' => ['label' => 'Cancellation rate threshold', 'type' => 'number', 'rules' => 'required|numeric|min:0|max:1'],
                        'velocity_orders_per_day' => ['label' => 'Orders per day velocity limit', 'type' => 'integer', 'rules' => 'required|integer|min:1|max:1000'],
                        'auto_hold_severity' => ['label' => 'Severity that holds commissions', 'type' => 'string', 'rules' => 'required|in:low,medium,high,critical'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Seed/refresh the registry from definitions. Never touches operator
     * config, status or enabled state on already-registered algorithms.
     */
    public function sync(): int
    {
        $count = 0;

        DB::transaction(function () use (&$count) {
            foreach ($this->definitions() as $key => $def) {
                $algorithm = Algorithm::firstOrNew(['key' => $key]);

                $algorithm->fill([
                    'name' => $def['name'],
                    'category' => $def['category'],
                    'description' => $def['description'],
                    'owner' => $def['owner'],
                    'is_implemented' => (bool) ($def['implemented'] ?? false),
                    'config_schema' => $def['config_schema'] ?? ['defaults' => [], 'fields' => []],
                ]);

                if (! $algorithm->exists) {
                    $algorithm->status = 'draft';
                    $algorithm->is_enabled = false;
                    $algorithm->version = 1;
                    $algorithm->config = $def['config_schema']['defaults'] ?? [];
                    $algorithm->meta = ['icon' => $def['icon'] ?? null];
                } else {
                    $algorithm->meta = array_merge(is_array($algorithm->meta) ? $algorithm->meta : [], ['icon' => $def['icon'] ?? null]);
                }

                $algorithm->save();

                if ($algorithm->versions()->where('version', $algorithm->version)->doesntExist()) {
                    AlgorithmVersion::create([
                        'algorithm_id' => $algorithm->id,
                        'version' => $algorithm->version,
                        'config' => $algorithm->config,
                        'status' => $algorithm->status,
                        'notes' => 'Registered from definition.',
                        'activated_at' => $algorithm->isActive() ? now() : null,
                    ]);
                }

                $count++;
            }
        });

        $this->flush();

        return $count;
    }

    public function find(string $key): ?Algorithm
    {
        return Algorithm::where('key', $key)->first();
    }

    public function configFor(string $key): array
    {
        $map = Cache::remember(self::CACHE_KEY, 300, function () {
            return Algorithm::query()->get()->mapWithKeys(function (Algorithm $a) {
                return [$a->key => [
                    'status' => $a->status,
                    'enabled' => $a->is_enabled,
                    'config' => $a->effectiveConfig(),
                ]];
            })->all();
        });

        return $map[$key]['config'] ?? [];
    }

    public function isActive(string $key): bool
    {
        $algorithm = Algorithm::where('key', $key)->first();

        return $algorithm ? $algorithm->isActive() : false;
    }

    /**
     * Validate a candidate config against the algorithm's declared schema.
     *
     * @throws ValidationException
     */
    public function validateConfig(Algorithm $algorithm, array $config, bool $partial = false): array
    {
        $fields = $algorithm->config_schema['fields'] ?? [];

        if (empty($fields)) {
            return $config;
        }

        $rules = [];
        foreach ($fields as $field => $meta) {
            $rule = $meta['rules'] ?? 'nullable';
            if ($partial) {
                $rule = str_replace('required', 'sometimes', $rule);
            }
            $rules[$field] = $rule;
        }

        return Validator::make($config, $rules)->validate();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
