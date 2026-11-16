<?php

namespace Tests\Feature;

use App\Models\Algorithm;
use App\Models\AutomationRunLog;
use App\Models\MarketplaceAnomaly;
use App\Models\MarketplaceAnomalyRule;
use App\Models\MarketplaceMetricSnapshot;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Product;
use App\Models\RecommendationEvent;
use App\Models\Workflow;
use App\Services\Intelligence\AnomalyDetectionService;
use App\Services\Intelligence\AutomationRunner;
use App\Services\Intelligence\RecommendationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Concerns\GrantsAdminAccess;
use Tests\TestCase;

class IntelligenceEngineTest extends TestCase
{
    use DatabaseTransactions, GrantsAdminAccess;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Cache::flush();
    }

    protected function visibleProductId(): int
    {
        return (int) Product::visible()
            ->whereHas('primaryVariant', fn ($q) => $q->where('stock', '>', 0))
            ->value('id');
    }

    /* ------------------------- Registry / governance ------------------------- */

    public function test_registry_registers_algorithms_and_only_marks_implemented(): void
    {
        $this->assertGreaterThanOrEqual(11, Algorithm::count());
        $this->assertTrue((bool) Algorithm::where('key', 'product_discovery')->value('is_implemented'));
        $this->assertTrue((bool) Algorithm::where('key', 'search_ranking')->value('is_implemented'));

        $unimplemented = Algorithm::where('is_implemented', false)->pluck('key')->all();
        $this->assertNotContains('product_discovery', $unimplemented);
        $this->assertNotContains('search_ranking', $unimplemented);
    }

    public function test_unimplemented_algorithm_cannot_be_activated(): void
    {
        $algorithm = Algorithm::create([
            'key' => 'stub_unimplemented_'.uniqid(),
            'name' => 'Stub Unimplemented Algorithm',
            'category' => 'planning',
            'description' => 'Registered for governance only.',
            'owner' => 'Tests',
            'status' => 'draft',
            'is_enabled' => false,
            'is_implemented' => false,
            'version' => 1,
            'config' => [],
            'config_schema' => ['defaults' => [], 'fields' => []],
        ]);

        $this->assertNotSame('active', $algorithm->status);

        $response = $this->actingAs($this->admin())
            ->post(route('admin.algorithms.activate', $algorithm));

        $response->assertSessionHasErrors('algorithm');
        $this->assertNotSame('active', $algorithm->fresh()->status);
    }

    public function test_implemented_algorithm_can_be_activated_and_disabled(): void
    {
        $algorithm = Algorithm::where('key', 'product_discovery')->firstOrFail();
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.algorithms.activate', $algorithm))
            ->assertSessionHasNoErrors();
        $this->assertSame('active', $algorithm->fresh()->status);

        $this->post(route('admin.algorithms.disable', $algorithm))
            ->assertSessionHasNoErrors();
        $this->assertSame('disabled', $algorithm->fresh()->status);
    }

    public function test_algorithm_config_update_creates_version(): void
    {
        $algorithm = Algorithm::where('key', 'product_discovery')->firstOrFail();
        $before = $algorithm->version;

        $this->actingAs($this->admin())
            ->put(route('admin.algorithms.config.update', $algorithm), [
                'config' => ['default_limit' => 9],
            ])
            ->assertSessionHasNoErrors();

        $algorithm->refresh();
        $this->assertSame($before + 1, (int) $algorithm->version);
        $this->assertSame(9, (int) $algorithm->effectiveConfig()['default_limit']);
        $this->assertDatabaseHas('algorithm_versions', [
            'algorithm_id' => $algorithm->id,
            'version' => (int) $algorithm->version,
        ]);
    }

    /* ------------------------------ Admin pages ------------------------------ */

    public function test_intelligence_admin_pages_render(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.algorithms.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.algorithms.show', Algorithm::firstOrFail()))->assertOk();
        $this->actingAs($admin)->get(route('admin.automation.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.automation.runs'))->assertOk();
        $this->actingAs($admin)->get(route('admin.intelligence.index'))->assertOk();
        $this->actingAs($admin)->get(route('admin.intelligence.anomalies'))->assertOk();
        $this->actingAs($admin)->get(route('admin.command-center.index'))->assertOk();
    }

    /* ----------------------------- Recommendations ---------------------------- */

    public function test_recommendations_return_only_eligible_in_stock_products(): void
    {
        $results = app(RecommendationService::class)->forSurface('new_arrivals', [], 8);

        $this->assertLessThanOrEqual(8, $results->count());
        foreach ($results as $product) {
            $this->assertInstanceOf(Product::class, $product);
            $this->assertNotNull($product->primaryVariant);
            $this->assertGreaterThan(0, $product->primaryVariant->stock);
        }
    }

    public function test_recommendations_respect_exclusions_and_seed_product(): void
    {
        $seed = $this->visibleProductId();
        $exclude = Product::visible()->where('id', '!=', $seed)->value('id');

        $results = app(RecommendationService::class)->forSurface('product_similar', [
            'product_id' => $seed,
            'exclude' => [$exclude],
        ], 12);

        $ids = $results->pluck('id')->all();
        $this->assertNotContains($seed, $ids);
        $this->assertNotContains((int) $exclude, array_map('intval', $ids));
    }

    public function test_recommendations_carry_a_score_and_signals(): void
    {
        $results = app(RecommendationService::class)->forSurface('category_trending', [], 6);

        if ($results->isNotEmpty()) {
            $first = $results->first();
            $this->assertNotNull($first->discovery_score);
            $this->assertIsArray($first->recommendation_signals);
        }
    }

    public function test_complementary_surface_returns_nothing_without_evidence(): void
    {
        $seed = $this->visibleProductId();

        $results = app(RecommendationService::class)->forSurface('product_complementary', [
            'product_id' => $seed,
        ], 12);

        $this->assertCount(0, $results);
    }

    public function test_association_rebuild_exposes_complementary_pairs(): void
    {
        $products = Product::visible()
            ->whereHas('primaryVariant', fn ($q) => $q->where('stock', '>', 0))
            ->orderBy('id')
            ->take(2)
            ->get();

        $this->assertCount(2, $products, 'Need at least two eligible products.');
        [$a, $b] = [$products[0], $products[1]];

        foreach (range(1, 2) as $ignored) {
            $order = Order::create(['total_amount' => 100, 'status' => 'completed']);
            OrderDetail::create(['order_id' => $order->id, 'product_id' => $a->id, 'quantity' => 1, 'price' => 50]);
            OrderDetail::create(['order_id' => $order->id, 'product_id' => $b->id, 'quantity' => 1, 'price' => 50]);
        }

        $this->actingAs($this->admin())
            ->post(route('admin.intelligence.associations.rebuild'), ['min_support' => 2])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_associations', [
            'product_id' => $a->id,
            'related_product_id' => $b->id,
            'type' => 'complementary',
        ]);

        \Illuminate\Support\Facades\Cache::flush();

        $results = app(RecommendationService::class)->forSurface('product_complementary', ['product_id' => $a->id], 12);

        $this->assertTrue(
            $results->pluck('id')->map(fn ($id) => (int) $id)->contains($b->id),
            'Complementary rail should surface the co-purchased product.'
        );
    }

    public function test_storefront_rails_render_when_engine_is_active(): void
    {
        Algorithm::where('key', 'product_discovery')->update(['status' => 'active', 'is_enabled' => true]);
        \Illuminate\Support\Facades\Cache::flush();

        $this->get('/')->assertOk()->assertSee('data-reco-surface="home_personalized"', false);

        $product = Product::visible()->firstOrFail();
        $this->get(route('product.show', $product->slug))
            ->assertOk()
            ->assertSee('data-reco-surface="product_similar"', false);
    }

    /* -------------------------------- Events --------------------------------- */

    public function test_event_intake_records_and_deduplicates_impressions(): void
    {
        $productId = $this->visibleProductId();

        $payload = [
            'type' => 'impression',
            'surface' => 'home_personalized',
            'product_id' => $productId,
            'session_id' => 'sess-'.uniqid(),
        ];

        $this->postJson(route('recommendations.event'), $payload)->assertOk()->assertJson(['ok' => true]);

        $event = RecommendationEvent::where('product_id', $productId)->latest('id')->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->is_valid);

        $this->postJson(route('recommendations.event'), $payload)->assertOk();
        $this->assertFalse((bool) RecommendationEvent::where('product_id', $productId)->latest('id')->first()->is_valid);
    }

    public function test_purchase_event_is_validated_against_real_orders(): void
    {
        $order = Order::whereNotNull('total_amount')->first();

        if ($order) {
            $this->postJson(route('recommendations.event'), [
                'type' => 'purchase',
                'surface' => 'home_personalized',
                'order_id' => $order->id,
            ])->assertOk();

            $event = RecommendationEvent::where('order_id', $order->id)->latest('id')->first();
            $this->assertNotNull($event);
            $this->assertTrue($event->is_valid);
            $this->assertSame((int) round(((float) $order->total_amount) * 100), (int) $event->value_minor);
        }

        $this->postJson(route('recommendations.event'), [
            'type' => 'purchase',
            'surface' => 'home_personalized',
            'order_id' => 99999999,
        ])->assertOk();

        $fake = RecommendationEvent::where('order_id', 99999999)->latest('id')->first();
        $this->assertNotNull($fake);
        $this->assertFalse((bool) $fake->is_valid);
        $this->assertSame('unknown_order', $fake->invalid_reason);
    }

    /* ------------------------------- Automation ------------------------------ */

    public function test_automation_run_records_success_and_logs(): void
    {
        $workflow = Workflow::where('action', 'refresh_marketplace_metrics')->firstOrFail();

        $run = app(AutomationRunner::class)->dispatch($workflow, ['days' => 1], 'test');

        $this->assertSame('succeeded', $run->status);
        $this->assertSame(1, $run->attempt);
        $this->assertGreaterThan(0, AutomationRunLog::where('run_id', $run->id)->count());
    }

    public function test_automation_failure_moves_to_dead_letter(): void
    {
        $workflow = Workflow::create([
            'name' => 'Broken workflow',
            'key' => 'broken_test_'.uniqid(),
            'action' => 'not_a_real_action',
            'definition' => ['steps' => [['action' => 'not_a_real_action']]],
            'trigger_type' => 'manual',
            'max_attempts' => 1,
            'is_active' => false,
        ]);

        $run = app(AutomationRunner::class)->dispatch($workflow, [], 'test');

        $this->assertSame('dead_letter', $run->status);
        $this->assertNotNull($run->error);
    }

    /* ------------------------------- Anomalies ------------------------------- */

    public function test_anomaly_detection_flags_a_metric_drop(): void
    {
        app(AnomalyDetectionService::class)->seedDefaultRules();

        $rule = MarketplaceAnomalyRule::where('key', 'orders_drop')->firstOrFail();
        $target = now()->startOfDay()->subDay();

        for ($i = 0; $i < 14; $i++) {
            MarketplaceMetricSnapshot::create([
                'metric' => 'orders',
                'scope' => 'global',
                'scope_id' => null,
                'period_date' => $target->copy()->subDays($i + 1)->toDateString(),
                'value' => 100,
            ]);
        }

        MarketplaceMetricSnapshot::create([
            'metric' => 'orders',
            'scope' => 'global',
            'scope_id' => null,
            'period_date' => $target->toDateString(),
            'value' => 5,
        ]);

        $result = app(AnomalyDetectionService::class)->detect($target);
        $this->assertGreaterThanOrEqual(1, $result['detected']);

        $anomaly = MarketplaceAnomaly::where('metric', 'orders')->where('rule_id', $rule->id)->latest('id')->first();
        $this->assertNotNull($anomaly);
        $this->assertSame('open', $anomaly->status);
    }

    public function test_anomaly_can_be_acknowledged_and_resolved(): void
    {
        app(AnomalyDetectionService::class)->seedDefaultRules();
        $rule = MarketplaceAnomalyRule::where('key', 'orders_drop')->firstOrFail();

        $anomaly = MarketplaceAnomaly::create([
            'rule_id' => $rule->id,
            'dedup_key' => 'test-'.uniqid(),
            'metric' => 'orders',
            'scope' => 'global',
            'scope_id' => null,
            'observed_value' => 5,
            'expected_value' => 100,
            'deviation' => -0.95,
            'severity' => 'warning',
            'status' => 'open',
            'detected_at' => now(),
        ]);

        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.intelligence.anomalies.acknowledge', $anomaly))
            ->assertSessionHasNoErrors();
        $this->assertSame('acknowledged', $anomaly->fresh()->status);

        $this->post(route('admin.intelligence.anomalies.resolve', $anomaly))
            ->assertSessionHasNoErrors();
        $this->assertSame('resolved', $anomaly->fresh()->status);
    }

    /* ----------------------------- Seeding ----------------------------- */

    public function test_intelligence_seeder_populates_algorithms_rules_and_workflows_idempotently(): void
    {
        Algorithm::query()->delete();

        $this->assertSame(0, Algorithm::count());

        $this->seed(\Database\Seeders\IntelligenceSeeder::class);

        $count = Algorithm::count();
        $this->assertGreaterThanOrEqual(11, $count);
        $this->assertGreaterThanOrEqual(1, MarketplaceAnomalyRule::count());
        $this->assertTrue(Workflow::where('key', 'recompute_associations_daily')->exists());

        // Re-running must not duplicate rows.
        $this->seed(\Database\Seeders\IntelligenceSeeder::class);
        $this->assertSame($count, Algorithm::count());
        $this->assertSame(0, Algorithm::select('key')->groupBy('key')->havingRaw('count(*) > 1')->count());
    }
}
