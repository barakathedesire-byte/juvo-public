<?php

namespace App\Http\Controllers\Rider;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Logistics\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function show(Order $order)
    {
        $rider = $this->rider();

        abort_unless((int) $order->assigned_driver_id === (int) $rider->id, 404);

        $order->load([
            'customer',
            'vendor',
            'pickupLocation',
            'shippingAddress',
            'details.product',
            'deliveryEvents',
        ]);

        return view('rider.deliveries.show', [
            'rider' => $rider,
            'order' => $order,
            'failureReasons' => Order::FAILURE_REASONS,
            'unreadCount' => $rider->unreadNotifications()->count(),
            'supportPhone' => getWebConfig('phone_number') ?: getWebConfig('contact_phone'),
        ]);
    }

    public function accept(Request $request, Order $order)
    {
        $this->deliveries->accept($order, $this->rider());

        return $this->respond($request, 'Delivery accepted.', route('rider.deliveries.show', $order), $order);
    }

    public function decline(Request $request, Order $order)
    {
        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->deliveries->decline($order, $this->rider(), $data['reason'] ?? null);

        return $this->respond($request, 'Delivery declined and returned to dispatch.', route('rider.orders'), $order);
    }

    public function pickup(Request $request, Order $order)
    {
        $this->deliveries->markPickedUp($order, $this->rider(), $request->string('note')->toString() ?: null);

        return $this->respond($request, 'Pickup confirmed.', route('rider.deliveries.show', $order), $order);
    }

    public function transit(Request $request, Order $order)
    {
        $this->deliveries->markInTransit($order, $this->rider(), $request->string('note')->toString() ?: null);

        return $this->respond($request, 'You are now out for delivery.', route('rider.deliveries.show', $order), $order);
    }

    public function deliver(Request $request, Order $order)
    {
        $data = $request->validate([
            'receiver_name' => 'required|string|max:120',
            'notes' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:5120',
            'cod_collected' => 'nullable|boolean',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $request->file('photo')->store('pods', 'public');
        }

        $this->deliveries->markDelivered($order, $this->rider(), $data);

        return $this->respond($request, 'Delivery completed. Thank you!', route('rider.orders'), $order);
    }

    public function fail(Request $request, Order $order)
    {
        $data = $request->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys(Order::FAILURE_REASONS)),
            'note' => 'nullable|string|max:1000',
        ]);

        $this->deliveries->markFailed($order, $this->rider(), $data['reason'], $data['note'] ?? null);

        return $this->respond($request, 'Failed attempt reported to dispatch.', route('rider.orders'), $order);
    }

    public function arrivedPickup(Order $order)
    {
        $this->deliveries->markArrivedAtPickup($order, $this->rider());

        return $this->respond(request(), 'Pickup arrival recorded.', route('rider.deliveries.show', $order), $order);
    }

    public function arrivedDropoff(Order $order)
    {
        $this->deliveries->markArrivedAtDropoff($order, $this->rider());

        return $this->respond(request(), 'Drop-off arrival recorded.', route('rider.deliveries.show', $order), $order);
    }

    private function respond(Request $request, string $message, string $url, Order $order)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'status' => $order->logistics_status,
                'redirect' => $url,
            ]);
        }

        return redirect()->to($url)->with('success', $message);
    }

    private function rider()
    {
        return Auth::guard('rider')->user();
    }
}
