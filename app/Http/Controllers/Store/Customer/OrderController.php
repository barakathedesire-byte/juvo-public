<?php

namespace App\Http\Controllers\Store\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected const RETURNABLE = ['delivered', 'completed'];

    public function index()
    {
        $customer = Auth::guard('customer')->user();

        $orders = Order::where('customer_id', $customer->id)
            ->with('details.product', 'returns')
            ->latest()
            ->paginate(10);

        return view('themes.xylo.customer.orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $customer = Auth::guard('customer')->user();

        abort_if($order->customer_id !== $customer->id, 403);

        $order->load([
            'details.product',
            'statusHistories',
            'returns',
            'shippingAddress',
            'vendor.shop',
        ]);

        return view('themes.xylo.customer.orders.show', compact('order'));
    }

    public function invoice(Order $order)
    {
        $customer = Auth::guard('customer')->user();

        abort_if($order->customer_id !== $customer->id, 403);

        $order->load([
            'details.product',
            'customer',
            'shippingAddress',
            'vendor.shop',
        ]);

        return view('themes.xylo.customer.orders.invoice', compact('order'));
    }

    public function returnRequest(Request $request, Order $order)
    {
        $customer = Auth::guard('customer')->user();

        abort_if($order->customer_id !== $customer->id, 403);

        abort_unless(in_array($order->status, self::RETURNABLE, true), 422, 'This order cannot be returned.');

        $open = Refund::where('order_id', $order->id)
            ->whereIn('status', ['requested', 'approved'])
            ->exists();

        abort_if($open, 422, 'A return is already in progress for this order.');

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        Refund::create([
            'payment_id' => Payment::where('order_id', $order->id)->latest()->value('id'),
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'vendor_id' => $order->vendor_id,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?: 'TZS',
            'status' => 'requested',
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Your return request has been submitted.');
    }
}
