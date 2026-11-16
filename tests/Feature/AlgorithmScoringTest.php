<?php

namespace Tests\Feature;

use App\Models\Algorithm;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Vendor;
use App\Services\Intelligence\AlgorithmRegistry;
use App\Services\Intelligence\DemandForecastService;
use App\Services\Intelligence\FinanceIntelligenceService;
use App\Services\Intelligence\FraudDetectionService;
use App\Services\Intelligence\OrderRoutingService;
use App\Services\Intelligence\RetentionService;
use App\Services\Intelligence\SupportAssistService;
use App\Services\Intelligence\VendorPerformanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class AlgorithmScoringTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function sampleOrder(): Order
    {
        return Order::orderByDesc('total_amount')->firstOrFail();
    }

    /* ------------------------- Registry ------------------------- */

    public function test_all_seven_new_algorithms_are_registered_as_implemented(): void
    {
        $keys = ['demand_forecasting', 'fraud_detection', 'retention', 'vendor_performance', 'order_routing', 'support_assist', 'finance_intelligence'];

        foreach ($keys as $key) {
            $this->assertArrayHasKey($key, app(AlgorithmRegistry::class)->definitions());
            $this->assertTrue(app(AlgorithmRegistry::class)->definitions()[$key]['implemented']);
            $this->assertNotEmpty(app(AlgorithmRegistry::class)->definitions()[$key]['config_schema']['defaults']);
            $this->assertNotEmpty(app(AlgorithmRegistry::class)->definitions()[$key]['config_schema']['fields']);
            $this->assertTrue((bool) Algorithm::where('key', $key)->value('is_implemented'));
        }
    }

    public function test_every_algorithm_definition_declares_a_config_schema(): void
    {
        foreach (app(AlgorithmRegistry::class)->definitions() as $key => $definition) {
            $this->assertNotEmpty($definition['config_schema']['defaults'], "Algorithm [{$key}] has no defaults.");
            $this->assertNotEmpty($definition['config_schema']['fields'], "Algorithm [{$key}] has no fields.");
        }
    }

    /* ------------------------- Demand forecasting ------------------------- */

    public function test_demand_forecast_returns_a_horizon_with_an_auditable_basis(): void
    {
        $service = app(DemandForecastService::class);
        $forecast = $service->forecast(null, null, 7);

        $this->assertCount(7, $forecast['days']);
        $this->assertArrayHasKey('confidence', $forecast);
        $this->assertGreaterThanOrEqual(0, $forecast['confidence']);
        $this->assertLessThanOrEqual(1, $forecast['confidence']);
        $this->assertArrayHasKey('average_daily_units', $forecast['basis']);
        $this->assertArrayHasKey('trend_slope_per_day', $forecast['basis']);

        foreach ($forecast['days'] as $day) {
            $this->assertArrayHasKey('date', $day);
            $this->assertGreaterThanOrEqual(0, $day['units']);
        }
    }

    public function test_demand_forecast_is_deterministic(): void
    {
        $service = app(DemandForecastService::class);

        $this->assertSame($service->forecast(null, null, 5), $service->forecast(null, null, 5));
    }

    /* ------------------------- Fraud detection ------------------------- */

    public function test_fraud_score_stays_bounded_and_explains_itself(): void
    {
        $score = app(FraudDetectionService::class)->scoreOrder($this->sampleOrder());

        $this->assertGreaterThanOrEqual(0, $score['score']);
        $this->assertLessThanOrEqual(100, $score['score']);
        $this->assertContains($score['band'], FraudDetectionService::BANDS);
        $this->assertIsArray($score['signals']);

        foreach ($score['signals'] as $signal) {
            $this->assertArrayHasKey('name', $signal);
            $this->assertArrayHasKey('points', $signal);
            $this->assertArrayHasKey('detail', $signal);
            $this->assertNotEmpty($signal['detail']);
        }
    }

    public function test_fraud_band_thresholds_are_ordered(): void
    {
        $config = app(AlgorithmRegistry::class)->configFor('fraud_detection');

        $this->assertLessThan($config['high_threshold'], $config['medium_threshold']);
        $this->assertLessThan($config['critical_threshold'], $config['high_threshold']);
    }

    /* ------------------------- Retention ------------------------- */

    public function test_retention_scores_a_customer_into_a_known_segment(): void
    {
        $customer = Customer::orderBy('id')->firstOrFail();
        $score = app(RetentionService::class)->scoreCustomer($customer);

        $this->assertContains($score['segment'], ['new', 'active', 'loyal', 'at_risk', 'dormant']);
        $this->assertGreaterThanOrEqual(0, $score['churn_probability']);
        $this->assertLessThanOrEqual(1, $score['churn_probability']);
        $this->assertGreaterThanOrEqual(0, $score['clv_estimate']);
        $this->assertArrayHasKey('recency_days', $score);
        $this->assertArrayHasKey('frequency', $score);
    }

    public function test_retention_is_deterministic(): void
    {
        $customer = Customer::orderBy('id')->firstOrFail();
        $service = app(RetentionService::class);

        $this->assertSame($service->scoreCustomer($customer), $service->scoreCustomer($customer));
    }

    /* ------------------------- Vendor performance ------------------------- */

    public function test_vendor_score_is_bounded_with_components_that_sum_to_it(): void
    {
        $vendor = Vendor::orderBy('id')->firstOrFail();
        $score = app(VendorPerformanceService::class)->scoreVendor($vendor);

        $this->assertGreaterThanOrEqual(0, $score['score']);
        $this->assertLessThanOrEqual(100, $score['score']);
        $this->assertContains($score['grade'], ['A', 'B', 'C', 'D', 'E']);
        $this->assertGreaterThanOrEqual(0, $score['confidence']);
        $this->assertLessThanOrEqual(1, $score['confidence']);

        $componentTotal = round(array_sum(array_column($score['components'], 'points')), 2);
        $this->assertEqualsWithDelta($score['score'], $componentTotal, 0.1);
    }

    public function test_vendor_leaderboard_is_ordered_weakest_first(): void
    {
        $leaderboard = app(VendorPerformanceService::class)->leaderboard(10);

        $scores = $leaderboard->pluck('score')->all();
        $sorted = $scores;
        sort($sorted);

        $this->assertSame($sorted, $scores);
    }

    /* ------------------------- Order routing ------------------------- */

    public function test_order_routing_reports_every_path_and_picks_only_an_available_one(): void
    {
        $routing = app(OrderRoutingService::class)->recommend($this->sampleOrder());

        $names = array_column($routing['paths'], 'name');
        $this->assertSame(['vendor_direct', 'delivery_partner', 'flat_rate'], $names);

        if ($routing['recommended'] !== null) {
            $recommended = collect($routing['paths'])->firstWhere('name', $routing['recommended']);
            $this->assertTrue($recommended['available']);
            $this->assertNotNull($recommended['score']);
        }

        foreach ($routing['paths'] as $path) {
            $this->assertNotEmpty($path['reasons']);
        }
    }

    /* ------------------------- Support assist ------------------------- */

    public function test_support_triage_suggests_a_priority_and_queue(): void
    {
        $ticket = SupportTicket::create([
            'ticket_number' => 'TST-'.uniqid(),
            'subject' => 'Where is my delivery',
            'category' => 'delivery',
            'priority' => 'high',
            'status' => 'open',
        ]);

        $triage = app(SupportAssistService::class)->triage($ticket);

        $this->assertContains($triage['suggested_priority'], ['low', 'medium', 'high', 'urgent']);
        $this->assertSame('logistics', $triage['suggested_queue']);
        $this->assertContains($triage['sla_risk'], ['on_track', 'at_risk', 'first_response_breached', 'resolution_breached']);
        $this->assertIsArray($triage['signals']);
        $this->assertArrayHasKey('canned_response_id', $triage['knowledge']);
    }

    public function test_support_triage_ranks_an_overdue_ticket_above_an_untouched_one(): void
    {
        $service = app(SupportAssistService::class);

        $fresh = SupportTicket::create([
            'ticket_number' => 'TST-'.uniqid(),
            'subject' => 'General question',
            'category' => 'other',
            'priority' => 'low',
            'status' => 'open',
        ]);

        $overdue = SupportTicket::create([
            'ticket_number' => 'TST-'.uniqid(),
            'subject' => 'Escalated problem',
            'category' => 'delivery',
            'priority' => 'urgent',
            'status' => 'open',
            'escalation_level' => 2,
            'reopened_count' => 1,
            'first_response_due_at' => now()->subDay(),
        ]);

        $this->assertGreaterThan(
            $service->triage($fresh)['score'],
            $service->triage($overdue)['score']
        );
    }

    /* ------------------------- Finance intelligence ------------------------- */

    public function test_finance_report_is_structured_and_bounded(): void
    {
        $report = app(FinanceIntelligenceService::class)->report(30);

        $this->assertSame(30, $report['window_days']);
        $this->assertArrayHasKey('gross_revenue', $report);
        $this->assertArrayHasKey('commission', $report);
        $this->assertArrayHasKey('payouts', $report);
        $this->assertArrayHasKey('concentration', $report);
        $this->assertNotEmpty($report['findings']);

        $this->assertGreaterThanOrEqual(0, $report['margin_rate']);
        $this->assertLessThanOrEqual(1, $report['margin_rate']);
        $this->assertGreaterThanOrEqual(0, $report['concentration']['top_vendor_share']);
        $this->assertLessThanOrEqual(1, $report['concentration']['top_vendor_share']);
    }

    /* ------------------------- Dry-run endpoint ------------------------- */

    public function test_admin_can_dry_run_each_new_algorithm(): void
    {
        $admin = $this->admin();

        foreach (['demand_forecasting', 'fraud_detection', 'retention', 'vendor_performance', 'order_routing', 'support_assist', 'finance_intelligence'] as $key) {
            $algorithm = Algorithm::where('key', $key)->firstOrFail();

            $this->actingAs($admin)
                ->post(route('admin.algorithms.test', $algorithm), ['limit' => 5])
                ->assertOk()
                ->assertJsonPath('ok', true);
        }
    }
}
