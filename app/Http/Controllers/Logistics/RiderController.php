<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Logistics\DeliveryEvent;
use App\Models\Rider\Rider;
use App\Services\Admin\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RiderController extends Controller
{
    public function show(Rider $rider)
    {
        $rider->load('kycReviewedBy');

        $orders = Order::where('assigned_driver_id', $rider->id)
            ->with(['customer', 'shippingAddress'])
            ->latest('assigned_at')
            ->limit(25)
            ->get();

        $assignedCount = Order::where('assigned_driver_id', $rider->id)->count();
        $deliveredCount = Order::where('assigned_driver_id', $rider->id)->where('logistics_status', 'delivered')->count();
        $acceptedCount = $rider->deliveryEvents()->where('type', DeliveryEvent::TYPE_ACCEPTED)->count();
        $declinedCount = $rider->deliveryEvents()->where('type', DeliveryEvent::TYPE_DECLINED)->count();
        $cancelledCount = Order::where('assigned_driver_id', $rider->id)->where('logistics_status', 'cancelled')->count();
        $returnedCount = Order::where('assigned_driver_id', $rider->id)->where('logistics_status', 'returned')->count();

        $metrics = [
            'active' => Order::where('assigned_driver_id', $rider->id)
                ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
                ->count(),
            'delivered' => $deliveredCount,
            'failed' => Order::where('assigned_driver_id', $rider->id)
                ->where('logistics_status', 'failed')
                ->count(),
            'cancelled' => $cancelledCount,
            'returned' => $returnedCount,
            'assigned_total' => $assignedCount,
            'accepted' => $acceptedCount,
            'declined' => $declinedCount,
            'acceptance_rate' => $assignedCount > 0 ? round(($acceptedCount / $assignedCount) * 100) : null,
            'completion_rate' => $assignedCount > 0 ? round(($deliveredCount / $assignedCount) * 100) : null,
            'cash_held' => (float) Order::where('assigned_driver_id', $rider->id)
                ->whereNotNull('cod_collected_at')
                ->whereNull('cod_remitted_at')
                ->sum('cod_amount'),
            'earnings' => (float) Order::where('assigned_driver_id', $rider->id)
                ->where('logistics_status', 'delivered')
                ->sum('delivery_fee'),
        ];

        $events = $rider->deliveryEvents()->with('order:id,logistics_tracking_id')->limit(20)->get();
        $documents = $rider->kyc_documents ?? [];
        $missingDocuments = $rider->getMissingKycDocuments();

        return view('logistics.riders.show', compact('rider', 'orders', 'metrics', 'events', 'documents', 'missingDocuments'));
    }

    public function approve(Request $request, Rider $rider)
    {
        $this->canManageRiders();

        $data = $request->validate(['note' => 'nullable|string|max:500']);

        DB::transaction(function () use ($rider, $data) {
            $before = $rider->only(['kyc_status', 'is_active']);

            $rider->forceFill([
                'kyc_status' => 'approved',
                'kyc_reviewed_at' => now(),
                'kyc_reviewed_by' => auth('logistics_admin')->id(),
                'kyc_rejection_reason' => null,
                'is_active' => true,
            ])->save();

            AuditLogger::record('logistics.rider.approved', $rider, $before, $rider->only(['kyc_status', 'is_active']), $data['note'] ?? null, auth('logistics_admin')->user());
        });

        return redirect()->route('logistics.riders.show', $rider)->with('success', 'Rider approved and activated.');
    }

    public function reject(Request $request, Rider $rider)
    {
        $this->canManageRiders();

        $data = $request->validate(['reason' => 'required|string|max:500']);

        DB::transaction(function () use ($rider, $data) {
            $before = $rider->only(['kyc_status', 'is_active', 'is_online']);

            $rider->forceFill([
                'kyc_status' => 'rejected',
                'kyc_reviewed_at' => now(),
                'kyc_reviewed_by' => auth('logistics_admin')->id(),
                'kyc_rejection_reason' => $data['reason'],
                'is_active' => false,
                'is_online' => false,
            ])->save();

            AuditLogger::record('logistics.rider.rejected', $rider, $before, $rider->only(['kyc_status', 'is_active', 'is_online']), $data['reason'], auth('logistics_admin')->user());
        });

        return redirect()->route('logistics.riders.show', $rider)->with('success', 'Rider KYC rejected.');
    }

    public function toggleActive(Request $request, Rider $rider)
    {
        $this->canManageRiders();

        $data = $request->validate(['reason' => 'nullable|string|max:500']);

        DB::transaction(function () use ($rider, $data) {
            $before = $rider->only(['is_active', 'is_online']);

            $rider->forceFill([
                'is_active' => ! $rider->is_active,
                'is_online' => false,
            ])->save();

            AuditLogger::record(
                $rider->is_active ? 'logistics.rider.reactivated' : 'logistics.rider.suspended',
                $rider,
                $before,
                $rider->only(['is_active', 'is_online']),
                $data['reason'] ?? null,
                auth('logistics_admin')->user(),
            );
        });

        return redirect()
            ->route('logistics.riders.show', $rider)
            ->with('success', $rider->is_active ? 'Rider reactivated.' : 'Rider suspended and taken offline.');
    }

    private function canManageRiders(): void
    {
        $admin = auth('logistics_admin')->user();

        abort_unless(
            $admin && (in_array($admin->role, ['super_admin', 'admin'], true)
                || $admin->hasPermission('logistics.riders')),
            403,
            'You do not have permission to manage riders.',
        );
    }
}
