<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SalesAttribution;
use App\Models\SalesRep;
use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    protected function agent(): SalesRep
    {
        return request()->attributes->get('agent');
    }

    protected function ownedOrderIds(SalesRep $agent)
    {
        return SalesAttribution::where('sales_rep_id', $agent->id)->pluck('order_id');
    }

    protected function ownedOrdersQuery(SalesRep $agent)
    {
        return Order::whereIn('id', $this->ownedOrderIds($agent));
    }

    protected function findOwnedOrder(SalesRep $agent, int $id): Order
    {
        $order = Order::findOrFail($id);

        if (! $this->ownedOrderIds($agent)->contains($order->id)) {
            abort(403);
        }

        return $order;
    }

    public function index(Request $request)
    {
        $agent = $this->agent();
        $query = $this->ownedOrdersQuery($agent)
            ->with(['customer', 'vendor', 'shippingAddress', 'statusHistories']);

        if ($search = trim((string) $request->query('q'))) {
            $query->where(function ($inner) use ($search) {
                $inner->where('id', 'like', '%'.$search.'%')
                    ->orWhereHas('customer', function ($q) use ($search) {
                        $q->where('name', 'like', '%'.$search.'%')
                            ->orWhere('phone', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($paymentStatus = $request->query('payment_status')) {
            $query->where('payment_status', $paymentStatus);
        }

        if ($deliveryStatus = $request->query('delivery_status')) {
            $query->where('logistics_status', $deliveryStatus);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        $sort = $request->query('sort', 'latest');
        match ($sort) {
            'total' => $query->orderByDesc('total_amount'),
            'status' => $query->orderBy('status'),
            default => $query->latest(),
        };

        $orders = $query->paginate(20)->withQueryString();
        $all = $this->ownedOrdersQuery($agent)->get();

        $metrics = [
            'total' => $all->count(),
            'awaiting' => $all->where('status', 'pending')->count(),
            'processing' => $all->whereIn('status', ['accepted', 'processing', 'packed'])->count(),
            'ready' => $all->where('status', 'ready_for_pickup')->count(),
            'dispatched' => $all->whereIn('status', ['dispatched', 'rider_arrived'])->count(),
            'delivered' => $all->whereIn('status', ['delivered', 'completed'])->count(),
            'cancelled' => $all->where('status', 'canceled')->count(),
            'returned' => $all->where('status', 'returned')->count(),
            'attention' => $all->whereIn('status', ['failed', 'returned'])->count() + $all->where('payment_status', 'failed')->count(),
        ];

        return view('agent.orders.index', compact('agent', 'orders', 'metrics'));
    }

    public function show(Order $order)
    {
        $agent = $this->agent();
        $order = $this->findOwnedOrder($agent, $order->id);

        $order->load([
            'customer',
            'vendor.shop',
            'details.product',
            'details.variant',
            'shippingAddress',
            'statusHistories',
            'returns',
        ]);

        $attribution = SalesAttribution::where('order_id', $order->id)->first();
        $supportTickets = SupportTicket::where('order_id', $order->id)->latest()->get();
        $issues = $supportTickets;

        return view('agent.orders.show', compact('agent', 'order', 'attribution', 'issues'));
    }

    public function reportIssue(Request $request, Order $order)
    {
        $agent = $this->agent();
        $order = $this->findOwnedOrder($agent, $order->id);

        $data = $request->validate([
            'issue_type' => 'required|string|max:60',
            'description' => 'required|string|max:5000',
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => 'AGT-'.strtoupper(Str::random(8)),
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'order_id' => $order->id,
            'subject' => 'Agent issue: '.$data['issue_type'],
            'category' => 'order_issue',
            'dispute_type' => $data['issue_type'],
            'priority' => 'normal',
            'status' => 'open',
            'resolution_notes' => $data['description'],
        ]);

        return back()->with('success', 'Issue reported. Reference: '.$ticket->ticket_number);
    }

    public function requestReturn(Request $request, Order $order)
    {
        $agent = $this->agent();
        $order = $this->findOwnedOrder($agent, $order->id);

        if (! in_array($order->status, ['delivered', 'completed'], true)) {
            return back()->with('error', 'This order cannot be returned at its current status.');
        }

        $open = Refund::where('order_id', $order->id)->whereIn('status', ['requested', 'approved'])->exists();
        if ($open) {
            return back()->with('error', 'A return is already in progress for this order.');
        }

        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);

        Refund::create([
            'payment_id' => Payment::where('order_id', $order->id)->latest()->value('id'),
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'amount' => $order->total_amount,
            'currency' => $order->currency ?: 'TZS',
            'status' => 'requested',
            'reason' => $data['reason'],
        ]);

        return back()->with('success', 'Return request submitted.');
    }

    public function requestCancellation(Request $request, Order $order)
    {
        $agent = $this->agent();
        $order = $this->findOwnedOrder($agent, $order->id);

        if (in_array($order->status, ['delivered', 'completed', 'canceled', 'returned'], true)) {
            return back()->with('error', 'This order cannot be cancelled at its current status.');
        }

        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);

        $ticket = SupportTicket::create([
            'ticket_number' => 'AGT-'.strtoupper(Str::random(8)),
            'customer_id' => $order->customer_id,
            'vendor_id' => $order->vendor_id,
            'order_id' => $order->id,
            'subject' => 'Cancellation request',
            'category' => 'cancellation_request',
            'dispute_type' => 'cancellation',
            'priority' => 'normal',
            'status' => 'open',
            'resolution_notes' => $data['reason'],
        ]);

        return back()->with('success', 'Cancellation request submitted. Reference: '.$ticket->ticket_number);
    }
}
