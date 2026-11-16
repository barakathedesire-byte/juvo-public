<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AdCampaign;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Vendor\VendorFinanceService;
use App\Services\Store\InventoryService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request, VendorFinanceService $finance, InventoryService $inventory)
    {
        $vendor = Auth::guard('vendor')->user();
        $vendorId = $vendor->id;

        // Configurable reporting period (defaults to the last 30 days).
        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : now()->subDays(29)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $ordersInRange = Order::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to]);

        $completedInRange = (clone $ordersInRange)->where('status', 'completed');

        $grossSales = (clone $completedInRange)->sum('total_amount');
        $commission = (float) $completedInRange->sum('commission_total');
        $refunds = (float) \App\Models\Refund::where('vendor_id', $vendorId)
            ->whereBetween('created_at', [$from, $to])
            ->where('status', 'approved')
            ->sum('amount');
        $netEarnings = round($grossSales - $commission - $refunds, 2);

        $orderCounts = Order::where('vendor_id', $vendorId)
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'canceled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status IN ('pending','accepted') THEN 1 ELSE 0 END) as requiring_action,
                SUM(CASE WHEN status IN ('accepted','processing','ready_for_pickup') THEN 1 ELSE 0 END) as pending_fulfillment,
                SUM(CASE WHEN status IN ('dispatched','delivered') THEN 1 ELSE 0 END) as in_delivery
            ")
            ->first();

        $data = [
            'todaySales' => Order::where('vendor_id', $vendorId)
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('total_amount'),
            'grossSales' => round($grossSales, 2),
            'commission' => round($commission, 2),
            'refunds' => round($refunds, 2),
            'netEarnings' => $netEarnings,
            'totalOrders' => (int) ($orderCounts->total ?? 0),
            'completedOrders' => (int) ($orderCounts->completed ?? 0),
            'cancelledOrders' => (int) ($orderCounts->cancelled ?? 0),
            'requiringAction' => (int) ($orderCounts->requiring_action ?? 0),
            'pendingFulfillment' => (int) ($orderCounts->pending_fulfillment ?? 0),
            'inDelivery' => (int) ($orderCounts->in_delivery ?? 0),
            'totalProducts' => Product::where('vendor_id', $vendorId)->count(),
            'publishedProducts' => Product::where('vendor_id', $vendorId)
                ->where('status', 1)
                ->where('visibility', 'public')
                ->whereNull('archived_at')
                ->count(),
        ];

        $balances = $finance->balances($vendor);

        // Inventory alerts (real data, no fabrication).
        $lowStock = $inventory->lowStock($vendorId)->take(5);
        $outOfStock = ProductVariant::whereHas('product', fn ($q) => $q->where('vendor_id', $vendorId))
            ->where('stock', 0)
            ->whereNotNull('low_stock_threshold')
            ->count();

        // Best performing products by revenue (all time).
        $bestProducts = DB::table('order_details')
            ->join('orders', 'orders.id', '=', 'order_details.order_id')
            ->where('orders.vendor_id', $vendorId)
            ->selectRaw('order_details.product_id, SUM(order_details.quantity) as qty, SUM(order_details.quantity * order_details.price) as revenue')
            ->groupBy('order_details.product_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get();

        $productIds = $bestProducts->pluck('product_id')->all();
        $products = Product::with('translation')->whereIn('id', $productIds)->get()->keyBy('id');
        $bestProducts = $bestProducts->map(function ($row) use ($products) {
            $row->name = $products[$row->product_id]->translation->name ?? 'Product #'.$row->product_id;

            return $row;
        });

        // Ad / sponsorship performance
        $adStats = [
            'spend' => AdCampaign::where('vendor_id', $vendorId)->sum('amount_paid'),
            'active' => AdCampaign::where('vendor_id', $vendorId)->whereIn('status', ['approved', 'active'])->count(),
            'pending' => AdCampaign::where('vendor_id', $vendorId)->where('status', 'pending')->count(),
            'sponsored' => Product::where('vendor_id', $vendorId)->where('is_sponsored', 1)->count(),
        ];

        $campaigns = AdCampaign::with('product.translation')
            ->where('vendor_id', $vendorId)
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        // Sales trend for the selected period (monthly buckets).
        $salesTrend = (clone $ordersInRange)
            ->where('status', 'completed')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(total_amount) as total")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get();
        $maxTrend = max(1, (float) $salesTrend->max('total'));

        $recentOrders = Order::where('vendor_id', $vendorId)
            ->with(['details.product.translation'])
            ->latest()
            ->limit(6)
            ->get();

        $recentTransactions = $finance->transactionsQuery($vendor)->limit(6)->get();

        $onboarding = $this->onboardingChecklist($vendor);

        return view('vendor.dashboard.index', compact(
            'vendor', 'data', 'balances', 'lowStock', 'outOfStock', 'bestProducts',
            'adStats', 'campaigns', 'salesTrend', 'maxTrend', 'recentOrders',
            'recentTransactions', 'onboarding', 'from', 'to'
        ));
    }

    /**
     * Real completion checks for the onboarding checklist. Every item maps to
     * persisted data; nothing is faked.
     */
    protected function onboardingChecklist($vendor): array
    {
        $items = [
            [
                'label' => 'Verify your business email',
                'done' => (bool) $vendor->email_verified_at,
            ],
            [
                'label' => 'Complete store profile (description & banner)',
                'done' => (bool) ($vendor->banner && \App\Models\Shop::where('vendor_id', $vendor->id)->whereNotNull('description')->exists()),
            ],
            [
                'label' => 'Upload verification documents',
                'done' => $vendor->verificationDocuments()->exists(),
            ],
            [
                'label' => 'Add a payout method',
                'done' => $vendor->payoutMethods()->exists(),
            ],
            [
                'label' => 'Publish your first product',
                'done' => Product::where('vendor_id', $vendor->id)->where('status', 1)->whereNull('archived_at')->exists(),
            ],
            [
                'label' => 'Get approved by the marketplace',
                'done' => $vendor->isApproved(),
            ],
        ];

        foreach ($items as &$item) {
            $item['remaining'] = $item['done'] ? 'Completed' : 'To do';
        }

        return $items;
    }
}
