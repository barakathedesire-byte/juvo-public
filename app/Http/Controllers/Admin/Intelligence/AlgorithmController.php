<?php

namespace App\Http\Controllers\Admin\Intelligence;

use App\Http\Controllers\Controller;
use App\Models\Algorithm;
use App\Models\AlgorithmVersion;
use App\Services\Intelligence\AlgorithmRegistry;
use App\Services\Intelligence\RecommendationAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class AlgorithmController extends Controller
{
    public function __construct(
        protected AlgorithmRegistry $registry,
    ) {}

    public function index(RecommendationAnalyticsService $analytics)
    {
        $algorithms = Algorithm::withCount('versions')->orderBy('category')->orderBy('name')->get();

        return view('admin.intelligence.algorithms.index', [
            'algorithms' => $algorithms,
            'groups' => $algorithms->groupBy('category'),
            'totals' => $analytics->totals(now()->subDays(30), now()),
        ]);
    }

    public function show(Algorithm $algorithm, RecommendationAnalyticsService $analytics)
    {
        $algorithm->load(['versions' => fn ($q) => $q->limit(20)]);

        return view('admin.intelligence.algorithms.show', [
            'algorithm' => $algorithm,
            'versions' => $algorithm->versions,
            'effectiveConfig' => $algorithm->effectiveConfig(),
            'schema' => $algorithm->config_schema ?? ['defaults' => [], 'fields' => []],
            'surfaceMetrics' => $analytics->bySurface(now()->subDays(30), now()),
        ]);
    }

    public function updateConfig(Request $request, Algorithm $algorithm, AlgorithmRegistry $registry)
    {
        $config = $request->input('config', []);
        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }

        $before = $algorithm->config;

        // Merge over the effective config so a partial submission still
        // validates against the full schema, then store only validated keys.
        $candidate = array_merge($algorithm->effectiveConfig(), (array) $config);
        $validated = $registry->validateConfig($algorithm, $candidate);

        $merged = $validated;
        $version = (int) $algorithm->version + 1;

        $algorithm->update([
            'config' => $merged,
            'version' => $version,
            'status' => $algorithm->status === 'active' ? 'testing' : $algorithm->status,
        ]);

        $algorithm->versions()->create([
            'version' => $version,
            'config' => $merged,
            'status' => 'draft',
            'notes' => 'Configuration updated.',
            'created_by' => $request->user()?->id,
        ]);

        \App\Services\Admin\AuditLogger::record('algorithm.config.updated', $algorithm, ['config' => $before], ['config' => $merged]);

        return back()->with('success', 'Configuration saved as version v'.$version.'. Activate to serve it.');
    }

    public function test(Request $request, Algorithm $algorithm)
    {
        if ($algorithm->key === 'search_ranking') {
            return $this->testSearch($request);
        }

        if ($algorithm->key !== 'product_discovery') {
            return $this->testAlgorithm($request, $algorithm);
        }

        $context = [
            'product_id' => (int) $request->input('product_id', 0) ?: null,
            'category_id' => (int) $request->input('category_id', 0) ?: null,
            'subject_id' => (int) $request->input('subject_id', 0) ?: null,
        ];

        $surface = $request->input('surface', 'home_personalized');

        $results = app(\App\Services\Intelligence\RecommendationService::class)
            ->forSurface($surface, $context, 12);

        return response()->json([
            'ok' => true,
            'surface' => $surface,
            'count' => $results->count(),
            'items' => $results->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->translation->name ?? null,
                'score' => $p->discovery_score ?? null,
                'signals' => $p->recommendation_signals ?? null,
            ])->values(),
        ]);
    }

    protected function testAlgorithm(Request $request, Algorithm $algorithm)
    {
        return match ($algorithm->key) {
            'demand_forecasting' => $this->testDemandForecasting($request),
            'fraud_detection' => $this->testFraudDetection($request),
            'retention' => $this->testRetention($request),
            'vendor_performance' => $this->testVendorPerformance($request),
            'order_routing' => $this->testOrderRouting($request),
            'support_assist' => $this->testSupportAssist($request),
            'finance_intelligence' => $this->testFinanceIntelligence($request),
            default => response()->json([
                'ok' => false,
                'message' => 'Dry-run is only available for implemented algorithms.',
            ], 422),
        };
    }

    protected function testDemandForecasting(Request $request)
    {
        $result = app(\App\Services\Intelligence\DemandForecastService::class)
            ->forecast(
                (int) $request->input('product_id', 0) ?: null,
                (int) $request->input('category_id', 0) ?: null,
                (int) $request->input('days', 7) ?: null,
            );

        return response()->json(['ok' => true] + $result);
    }

    protected function testFraudDetection(Request $request)
    {
        $service = app(\App\Services\Intelligence\FraudDetectionService::class);

        if ($orderId = (int) $request->input('order_id', 0)) {
            $order = \App\Models\Order::find($orderId);
            if (! $order) {
                return response()->json(['ok' => false, 'message' => 'Order not found.'], 404);
            }

            return response()->json(['ok' => true, 'orders' => [$service->scoreOrder($order)]]);
        }

        return response()->json([
            'ok' => true,
            'count' => $service->flagged((int) $request->input('limit', 10))->count(),
            'orders' => $service->flagged((int) $request->input('limit', 10)),
        ]);
    }

    protected function testRetention(Request $request)
    {
        $service = app(\App\Services\Intelligence\RetentionService::class);

        if ($customerId = (int) $request->input('customer_id', 0)) {
            $customer = \App\Models\Customer::find($customerId);
            if (! $customer) {
                return response()->json(['ok' => false, 'message' => 'Customer not found.'], 404);
            }

            return response()->json(['ok' => true, 'customers' => [$service->scoreCustomer($customer)]]);
        }

        $atRisk = $service->atRisk((int) $request->input('limit', 10));

        return response()->json(['ok' => true, 'count' => $atRisk->count(), 'customers' => $atRisk]);
    }

    protected function testVendorPerformance(Request $request)
    {
        $service = app(\App\Services\Intelligence\VendorPerformanceService::class);

        if ($vendorId = (int) $request->input('vendor_id', 0)) {
            $vendor = \App\Models\Vendor::find($vendorId);
            if (! $vendor) {
                return response()->json(['ok' => false, 'message' => 'Vendor not found.'], 404);
            }

            return response()->json(['ok' => true, 'vendors' => [$service->scoreVendor($vendor)]]);
        }

        $leaderboard = $service->leaderboard((int) $request->input('limit', 10));

        return response()->json(['ok' => true, 'count' => $leaderboard->count(), 'vendors' => $leaderboard]);
    }

    protected function testOrderRouting(Request $request)
    {
        $orderId = (int) $request->input('order_id', 0);
        $order = $orderId ? \App\Models\Order::find($orderId) : \App\Models\Order::orderByDesc('id')->first();

        if (! $order) {
            return response()->json(['ok' => false, 'message' => 'No order available to route.'], 404);
        }

        return response()->json([
            'ok' => true,
            'routing' => app(\App\Services\Intelligence\OrderRoutingService::class)->recommend($order),
        ]);
    }

    protected function testSupportAssist(Request $request)
    {
        $service = app(\App\Services\Intelligence\SupportAssistService::class);

        if ($ticketId = (int) $request->input('ticket_id', 0)) {
            $ticket = \App\Models\SupportTicket::find($ticketId);
            if (! $ticket) {
                return response()->json(['ok' => false, 'message' => 'Ticket not found.'], 404);
            }

            return response()->json(['ok' => true, 'tickets' => [$service->triage($ticket)]]);
        }

        $backlog = $service->backlog((int) $request->input('limit', 10));

        return response()->json(['ok' => true, 'count' => $backlog->count(), 'tickets' => $backlog]);
    }

    protected function testFinanceIntelligence(Request $request)
    {
        $result = app(\App\Services\Intelligence\FinanceIntelligenceService::class)
            ->report((int) $request->input('days', 0) ?: null);

        return response()->json(['ok' => true] + $result);
    }

    protected function testSearch(Request $request)
    {
        $query = (string) $request->input('q', '');

        $result = app(\App\Services\Search\SearchService::class)->search($query, [
            'locale' => $request->input('locale', App::getLocale()),
            'category' => (array) $request->input('category', []),
            'brand' => (array) $request->input('brand', []),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'sort' => $request->input('sort'),
            'per_page' => 10,
            'include_sponsored' => false,
        ]);

        return response()->json([
            'ok' => true,
            'query' => $result['query'],
            'intent' => $result['meta']['intent'],
            'total' => $result['meta']['total'],
            'latency_ms' => $result['meta']['latency_ms'],
            'index_used' => $result['meta']['index_used'],
            'did_you_mean' => $result['meta']['did_you_mean'],
            'count' => $result['products']->count(),
            'items' => collect($result['products']->items())->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->translation->name ?? null,
                'score' => $p->search_score ?? null,
                'signals' => $p->search_signals ?? null,
            ])->values(),
        ]);
    }

    public function activate(Request $request, Algorithm $algorithm)
    {
        if (! $algorithm->is_implemented) {
            return back()->withErrors(['algorithm' => 'This algorithm is registered for governance but has no implementation yet. It cannot be activated.']);
        }

        $config = $algorithm->effectiveConfig();
        try {
            \app(AlgorithmRegistry::class)->validateConfig($algorithm, $config);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $before = ['status' => $algorithm->status, 'is_enabled' => $algorithm->is_enabled];

        $algorithm->update(['status' => 'active', 'is_enabled' => true]);
        $algorithm->versions()->where('version', $algorithm->version)->update(['status' => 'active', 'activated_at' => now()]);

        \app(AlgorithmRegistry::class)->flush();
        \App\Services\Admin\AuditLogger::record('algorithm.activated', $algorithm, $before, ['status' => 'active', 'is_enabled' => true]);

        return back()->with('success', $algorithm->name.' activated.');
    }

    public function disable(Request $request, Algorithm $algorithm)
    {
        $before = ['status' => $algorithm->status, 'is_enabled' => $algorithm->is_enabled];

        $algorithm->update(['status' => 'disabled', 'is_enabled' => false]);
        \app(AlgorithmRegistry::class)->flush();
        \App\Services\Admin\AuditLogger::record('algorithm.disabled', $algorithm, $before, ['status' => 'disabled', 'is_enabled' => false]);

        return back()->with('success', $algorithm->name.' disabled.');
    }

    public function rollback(Request $request, Algorithm $algorithm, AlgorithmVersion $version)
    {
        if ($version->algorithm_id !== $algorithm->id) {
            abort(404);
        }

        $before = ['version' => $algorithm->version, 'config' => $algorithm->config];

        $newVersion = (int) $algorithm->version + 1;

        $algorithm->update([
            'config' => $version->config,
            'version' => $newVersion,
        ]);

        $algorithm->versions()->create([
            'version' => $newVersion,
            'config' => $version->config,
            'status' => $algorithm->status,
            'notes' => 'Rolled back to configuration from v'.$version->version.'.',
            'created_by' => $request->user()?->id,
        ]);

        \app(AlgorithmRegistry::class)->flush();
        \App\Services\Admin\AuditLogger::record('algorithm.rolled_back', $algorithm, $before, ['restored_from' => $version->version, 'version' => $newVersion]);

        return back()->with('success', 'Rolled back to configuration from v'.$version->version.'.');
    }
}
