<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Logistics\DeliveryEvent;
use App\Services\Admin\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Rider\Rider;
use App\Services\Logistics\DeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries) {}

    public function show(Order $order)
    {
        $order->load([
            'customer',
            'vendor',
            'pickupLocation',
            'shippingAddress',
            'details.product',
            'assignedRider',
            'deliveryEvents.rider',
        ]);

        $riders = Rider::query()
            ->where('is_active', true)
            ->where('kyc_status', 'approved')
            ->orderByDesc('is_online')
            ->orderByDesc('rating')
            ->orderBy('first_name')
            ->get();

        return view('logistics.orders.show', [
            'order' => $order,
            'riders' => $riders,
            'failureReasons' => Order::FAILURE_REASONS,
        ]);
    }

    public function assign(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'rider_id' => 'required|integer|exists:riders,id',
            'note' => 'nullable|string|max:500',
        ]);

        $rider = Rider::findOrFail($data['rider_id']);

        $this->deliveries->assign($order, $rider, $this->admin(), $data['note'] ?? null);

        return redirect()
            ->route('logistics.orders.show', $order)
            ->with('success', 'Assigned to '.$rider->full_name.'.');
    }

    public function unassign(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        $this->deliveries->unassign($order, $this->admin(), $data['reason'] ?? null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Rider removed from delivery.');
    }

    public function pickup(Request $request, Order $order)
    {
        $this->canDispatch();

        $this->deliveries->markPickedUp($order, $this->admin(), $request->string('note')->toString() ?: null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Marked as picked up.');
    }

    public function transit(Request $request, Order $order)
    {
        $this->canDispatch();

        $this->deliveries->markInTransit($order, $this->admin(), $request->string('note')->toString() ?: null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Delivery is out for delivery.');
    }

    public function deliver(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'receiver_name' => 'required|string|max:120',
            'notes' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:5120',
            'cod_collected' => 'nullable|boolean',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_url'] = $request->file('photo')->store('pods', 'public');
        }

        $this->deliveries->markDelivered($order, $this->admin(), $data);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Delivery marked as delivered.');
    }

    public function fail(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'reason' => 'required|string|in:'.implode(',', array_keys(Order::FAILURE_REASONS)),
            'note' => 'nullable|string|max:1000',
        ]);

        $this->deliveries->markFailed($order, $this->admin(), $data['reason'], $data['note'] ?? null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Failure recorded.');
    }

    public function reschedule(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'scheduled_for' => 'required|date|after_or_equal:today',
            'note' => 'nullable|string|max:1000',
        ]);

        $this->deliveries->reschedule($order, $this->admin(), $data['scheduled_for'], $data['note'] ?? null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Delivery rescheduled.');
    }

    public function returnToSender(Request $request, Order $order)
    {
        $this->canDispatch();

        $this->deliveries->markReturned($order, $this->admin(), $request->string('note')->toString() ?: null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Marked as returned to sender.');
    }

    public function cancel(Request $request, Order $order)
    {
        $this->canDispatch();

        $this->deliveries->cancel($order, $this->admin(), $request->string('note')->toString() ?: null);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Delivery cancelled.');
    }

    public function note(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate(['note' => 'required|string|max:1000']);

        $this->deliveries->addNote($order, $this->admin(), $data['note']);

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Internal note added.');
    }

    public function collectCod(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $this->deliveries->recordCodCollection(
            $order,
            $this->admin(),
            isset($data['amount']) ? (float) $data['amount'] : null,
            $data['note'] ?? null,
        );

        return redirect()->route('logistics.finance.index')->with('success', 'Cash collection recorded.');
    }

    public function receiveCod(Request $request, Order $order)
    {
        $this->canFinance();

        $data = $request->validate([
            'reference' => 'required|string|max:100',
            'note' => 'nullable|string|max:500',
        ]);

        $this->deliveries->remitCod($order, $this->admin(), $data['reference'], $data['note'] ?? null);

        return redirect()->route('logistics.finance.index')->with('success', 'Cash reconciliation recorded.');
    }

    public function events(Order $order)
    {
        return response()->json([
            'order_id' => $order->id,
            'status' => $order->logistics_status,
            'events' => $order->deliveryEvents()->with('rider:id,first_name,last_name')->limit(100)->get(),
        ]);
    }

    private function admin()
    {
        return auth('logistics_admin')->user();
    }

    public function updatePriority(Request $request, Order $order)
    {
        $this->canDispatch();

        $data = $request->validate([
            'priority' => 'required|string|in:low,normal,high,urgent',
        ]);

        DB::transaction(function () use ($order, $data) {
            $before = $order->only(['logistics_priority']);
            $order->forceFill(['logistics_priority' => $data['priority']])->save();

            DeliveryEvent::create([
                'order_id' => $order->id,
                'rider_id' => $order->assigned_driver_id,
                'type' => DeliveryEvent::TYPE_NOTE,
                'from_status' => $order->logistics_status,
                'to_status' => $order->logistics_status,
                'actor_type' => get_class($this->admin()),
                'actor_id' => $this->admin()->id,
                'actor_name' => $this->admin()->name,
                'note' => 'Priority set to '.Str::headline($data['priority']).'.',
            ]);

            AuditLogger::record('logistics.order.priority_updated', $order, $before, $order->only(['logistics_priority']), null, $this->admin());
        });

        return redirect()->route('logistics.orders.show', $order)->with('success', 'Delivery priority updated.');
    }

    public function autoAssign(Request $request, Order $order)
    {
        $this->canDispatch();

        $riders = Rider::query()
            ->withCount(['assignedOrders as active_deliveries_count' => fn ($query) => $query->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])])
            ->where('is_active', true)
            ->where('kyc_status', 'approved')
            ->where('background_check_status', '!=', 'flagged')
            ->where(function ($query) {
                $query->whereNull('license_expiry')->orWhere('license_expiry', '>=', now()->toDateString());
            })
            ->get();

        $rider = $this->recommendedRider($order, $riders);

        if (! $rider) {
            return redirect()->route('logistics.dispatch.index')->with('error', 'No eligible rider is available for automatic assignment.');
        }

        try {
            $this->deliveries->assign($order, $rider, $this->admin(), 'Automatically assigned by dispatch workspace.');
        } catch (ValidationException $exception) {
            return redirect()->route('logistics.dispatch.index')->withErrors($exception->errors())->with('error', 'Automatic assignment could not be completed.');
        }

        return redirect()->route('logistics.dispatch.index')->with('success', 'Assigned to '.$rider->full_name.'.');
    }

    private function recommendedRider(Order $order, $riders): ?Rider
    {
        $destinationCity = strtolower(trim((string) ($order->shippingAddress?->city ?? '')));
        $destinationState = strtolower(trim((string) ($order->shippingAddress?->state ?? '')));
        $pickupName = strtolower(trim((string) ($order->pickupLocation?->name ?? '')));

        $candidates = $riders->filter(fn (Rider $rider) => $rider->isEligibleForWork());

        if ($candidates->isEmpty()) {
            return null;
        }

        return $candidates->sortByDesc(function (Rider $rider) use ($destinationCity, $destinationState, $pickupName) {
            $score = 0;

            if ($rider->is_online) {
                $score += 40;
            }

            $score += max(0, 30 - ((int) $rider->active_deliveries_count * 10));

            $riderCity = strtolower(trim((string) $rider->city));
            $riderState = strtolower(trim((string) $rider->state));

            if ($destinationCity !== '' && $riderCity === $destinationCity) {
                $score += 20;
            }

            if ($destinationState !== '' && $riderState === $destinationState) {
                $score += 10;
            }

            if ($pickupName !== '' && $riderCity !== '' && str_contains($pickupName, $riderCity)) {
                $score += 8;
            }

            $score += (int) round((float) $rider->rating * 2);

            if ($rider->license_expiry && $rider->license_expiry->isFuture()) {
                $score += 5;
            }

            return $score;
        })->sortBy('active_deliveries_count')->first();
    }

    private function canDispatch(): void
    {
        $admin = $this->admin();

        abort_unless(
            $admin && (in_array($admin->role, ['super_admin', 'admin', 'dispatcher'], true)
                || $admin->hasPermission('logistics.dispatch')),
            403,
            'You do not have permission to dispatch deliveries.',
        );
    }

    private function canFinance(): void
    {
        $admin = $this->admin();

        abort_unless(
            $admin && (in_array($admin->role, ['super_admin', 'admin'], true)
                || $admin->hasPermission('logistics.finance')),
            403,
            'You do not have permission to reconcile cash.',
        );
    }
}
