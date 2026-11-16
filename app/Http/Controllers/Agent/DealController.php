<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\AgentPromotionShare;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\SalesRep;
use App\Models\Vendor;
use Illuminate\Http\Request;

class DealController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    public function index(Request $request)
    {
        $agent = $this->agent();

        $query = Promotion::with(['product.translation', 'product.variants', 'vendor']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where('title', 'like', '%'.$search.'%');
        }

        $filter = $request->query('filter', 'active');
        $now = now();

        if ($filter === 'active') {
            $query->running();
        } elseif ($filter === 'upcoming') {
            $query->where('starts_at', '>', $now);
        } elseif ($filter === 'expiring') {
            $query->running()->where('ends_at', '<=', $now->copy()->addDays(7));
        }

        if ($categoryId = $request->query('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($vendorId = $request->query('vendor_id')) {
            $query->where('vendor_id', $vendorId);
        }

        $promotions = $query->latest()->paginate(24)->withQueryString();

        $metrics = [
            'active' => Promotion::running()->count(),
            'upcoming' => Promotion::where('starts_at', '>', $now)->count(),
            'expiring' => Promotion::running()->where('ends_at', '<=', $now->copy()->addDays(7))->count(),
            'products_on_offer' => Promotion::running()->whereNotNull('product_id')->distinct('product_id')->count('product_id'),
        ];

        $categories = Category::orderBy('id')->get();
        $vendors = Vendor::where('status', 'active')->where('is_approved', 1)->orderBy('shop_name')->get();

        $shares = AgentPromotionShare::where('sales_rep_id', $agent->id)->with('promotion', 'product')->latest()->take(20)->get();

        return view('agent.deals.index', compact('agent', 'promotions', 'metrics', 'categories', 'vendors', 'shares', 'filter'));
    }

    public function show(Promotion $promotion)
    {
        $agent = $this->agent();

        $promotion->load(['product.translation', 'product.variants', 'product.images', 'vendor']);

        $eligibleProducts = collect();
        if ($promotion->product_id) {
            $eligibleProducts = Product::visible()->where('id', $promotion->product_id)->with(['translation', 'variants'])->get();
        } elseif ($promotion->category_id) {
            $eligibleProducts = Product::visible()->where('category_id', $promotion->category_id)->with(['translation', 'variants'])->take(24)->get();
        } else {
            $eligibleProducts = Product::visible()->with(['translation', 'variants'])->take(24)->get();
        }

        return view('agent.deals.show', compact('agent', 'promotion', 'eligibleProducts'));
    }

    public function share(Request $request, Promotion $promotion)
    {
        $agent = $this->agent();

        $data = $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'channel' => 'nullable|string|max:40',
        ]);

        AgentPromotionShare::create([
            'sales_rep_id' => $agent->id,
            'promotion_id' => $promotion->id,
            'product_id' => $data['product_id'] ?? $promotion->product_id,
            'channel' => $data['channel'] ?? 'link',
        ]);

        $product = $data['product_id'] ? Product::find($data['product_id']) : $promotion->product;

        if ($product) {
            return redirect()->route('agent.shop.show', $product)
                ->with('success', 'Promotion shared. Product link ready to send to the customer.');
        }

        return redirect()->route('agent.deals.show', $promotion)
            ->with('success', 'Promotion shared.');
    }
}
