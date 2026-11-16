<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Services\Store\OrderService;
use App\Models\Customer;
use App\Models\SalesAttribution;
use App\Models\Order;
use App\Models\Promotion;
use App\Models\SalesLead;
use App\Models\SupportTicket;
use Illuminate\Http\Request;

class WorkspaceController extends Controller
{
    protected function agent()
    {
        return request()->attributes->get('agent');
    }

    public function customers()
    {
        $agent = $this->agent();
        $customerIds = SalesAttribution::where('sales_rep_id', $agent->id)
            ->with('order')
            ->get()
            ->pluck('order.customer_id')
            ->filter()
            ->unique();

        $customers = Customer::whereIn('id', $customerIds)->latest()->paginate(20);

        return view('agent.customers', compact('agent', 'customers'));
    }

    public function leads()
    {
        $agent = $this->agent();
        $leads = SalesLead::where('sales_rep_id', $agent->id)->latest()->paginate(20);

        return view('agent.leads', compact('agent', 'leads'));
    }

    public function shop()
    {
        $agent = $this->agent();
        $products = \App\Models\Product::visible()->with('translation')->latest()->take(24)->get();

        return view('agent.shop', compact('agent', 'products'));
    }

    public function placeOrder(Request $request)
    {
        $agent = $this->agent();
        $customer = null;

        if ($request->filled('customer_id')) {
            $customer = \App\Models\Customer::find($request->integer('customer_id'));
        }

        $customers = \App\Models\Customer::where('sales_rep_id', $agent->id)->with('addresses')->orderBy('name')->get();
        $products = \App\Models\Product::visible()->with(['translation', 'variants'])->latest()->take(50)->get();
        $gateways = \App\Models\PaymentGateway::with('configs')->where('is_active', 1)->get();

        $selectedVariantId = $request->integer('variant_id') ?: null;
        $selectedQuantity = max(1, (int) $request->query('quantity', 1));

        return view('agent.place-order', compact('agent', 'customer', 'customers', 'products', 'gateways', 'selectedVariantId', 'selectedQuantity'));
    }

    public function placeOrderStore(Request $request)
    {
        $agent = $this->agent();

        $data = $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'variant_ids' => 'required|array|min:1',
            'variant_ids.*' => 'required|exists:product_variants,id',
            'quantities' => 'required|array|min:1',
            'quantities.*' => 'required|integer|min:1',
            'payment_method' => 'required|string|max:50',
            'region' => 'nullable|string|max:120',
            'location_id' => 'nullable|exists:locations,id',
            'delivery_address' => 'nullable|string|max:1000',
            'delivery_instructions' => 'nullable|string|max:1000',
            'contact_phone' => 'nullable|string|max:30',
            'idempotency_key' => 'required|string|max:100',
        ]);

        if (! \Illuminate\Support\Facades\Cache::add('agent_order_submit:'.$data['idempotency_key'], 1, 300)) {
            return back()->with('error', 'This order is already being processed. Please wait.');
        }

        $customer = \App\Models\Customer::findOrFail($data['customer_id']);

        $lines = [];
        foreach ($data['variant_ids'] as $index => $variantId) {
            $quantity = (int) ($data['quantities'][$index] ?? 1);
            $variant = ProductVariant::with('product')->find($variantId);

            if (! $variant || ! $variant->product || ! $variant->product->visible()->exists()) {
                return back()->withInput()->with('error', 'One of the selected products is not available.');
            }

            if ((int) $variant->stock < $quantity) {
                return back()->withInput()->with('error', 'Insufficient stock for '.$variant->product->translation?->name.'.');
            }

            $lines[] = [
                'product' => $variant->product,
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => (float) ($variant->discount_price ?? $variant->price),
            ];
        }

        $shippingData = [
            'name' => $customer->name,
            'phone' => ($data['contact_phone'] ?? null) ?: $customer->phone,
            'address' => ($data['delivery_address'] ?? null) ?: ($customer->delivery_address ?: $customer->address),
            'city' => $customer->location?->name,
            'state' => ($data['region'] ?? null) ?: $customer->region?->name,
            'region_id' => $customer->region_id,
            'location_id' => ($data['location_id'] ?? null) ?: $customer->location_id,
            'country' => 'Tanzania',
        ];

        $orders = app(OrderService::class)->createOrders($lines, $shippingData, null, [
            'customer_id' => $customer->id,
            'guest_email' => $customer->email,
            'is_guest' => false,
            'payment_method' => $data['payment_method'],
            'status' => 'pending',
            'payment_status' => 'pending',
            'region' => ($data['region'] ?? null) ?: $customer->region?->name,
            'location_id' => ($data['location_id'] ?? null) ?: $customer->location_id,
            'fulfillment_type' => 'delivery',
            'delivery_address' => ($data['delivery_address'] ?? null) ?: ($customer->delivery_address ?: $customer->address),
            'note' => trim(($data['delivery_instructions'] ?? '').' '.(($data['contact_phone'] ?? '') ? 'Contact: '.$data['contact_phone'] : '')),
            'notify' => true,
        ]);

        foreach ($orders as $order) {
            try {
                app(\App\Services\Sales\AgentAttributionService::class)->attribute($order, $agent, 'assisted');
            } catch (\Throwable $e) {
                report($e);
            }
        }

        \Illuminate\Support\Facades\Cache::forget('agent_order_submit:'.$data['idempotency_key']);

        return redirect()->route('agent.orders.index')
            ->with('success', 'Order placed successfully for '.$customer->name.'.');
    }

    public function orders(Request $request)
    {
        $agent = $this->agent();
        $status = $request->query('status');

        $orderIds = SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');
        $query = Order::whereIn('id', $orderIds)
            ->with(['customer'])
            ->latest();

        if ($status) {
            $query->where('status', $status);
        }

        $orders = $query->paginate(20);

        return view('agent.orders', compact('agent', 'orders', 'status'));
    }

    public function performance()
    {
        $agent = $this->agent();
        $orderIds = SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');
        $orders = Order::whereIn('id', $orderIds)->get();

        $metrics = [
            'total_orders' => $orders->count(),
            'delivered_orders' => $orders->where('status', 'delivered')->count(),
            'cancelled_orders' => $orders->where('status', 'cancelled')->count(),
            'total_sales' => (float) $orders->sum('total_amount'),
            'delivered_sales' => (float) $orders->where('status', 'delivered')->sum('total_amount'),
            'customers' => Order::whereIn('id', $orderIds)
                ->whereNotNull('customer_id')->distinct('customer_id')->count('customer_id'),
        ];

        return view('agent.performance', compact('agent', 'metrics'));
    }

    public function deals()
    {
        $agent = $this->agent();
        $promotions = Promotion::where('status', 'active')->latest()->take(20)->get();

        return view('agent.deals', compact('agent', 'promotions'));
    }

    public function academy()
    {
        $agent = $this->agent();

        return view('agent.academy', compact('agent'));
    }

    public function community()
    {
        $agent = $this->agent();

        return view('agent.community', compact('agent'));
    }

    public function support()
    {
        $agent = $this->agent();
        $tickets = SupportTicket::latest()->take(20)->get();

        return view('agent.support', compact('agent', 'tickets'));
    }
}
