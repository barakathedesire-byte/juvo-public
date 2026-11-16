<?php

namespace App\Http\Controllers\Logistics;

use App\Http\Controllers\Controller;
use App\Models\IntegrationEvent;
use App\Services\Integration\IntegrationEventService;
use Illuminate\Http\Request;

class IntegrationController extends Controller
{
    public function index(Request $request)
    {
        $this->canView();
        $query = IntegrationEvent::latest();
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($type = $request->string('event_type')->toString()) {
            $query->where('event_type', $type);
        }
        $events = $query->paginate(25)->withQueryString();

        $kpis = [
            'total' => IntegrationEvent::count(),
            'processed' => IntegrationEvent::where('status', 'processed')->count(),
            'failed' => IntegrationEvent::where('status', 'failed')->count(),
            'pending' => IntegrationEvent::whereIn('status', ['pending', 'processing'])->count(),
        ];

        return view('logistics.integration.index', compact('events', 'kpis'));
    }

    public function retry(IntegrationEvent $event, IntegrationEventService $service)
    {
        $this->canView();
        $event->update(['status' => 'pending', 'error' => null]);
        $service->dispatch($event->event_type, $event->payload ?? [], $event->source, $event->target, $event->event_id);

        return back()->with('success', 'Integration event retried.');
    }

    private function canView(): void
    {
        $admin = auth('logistics_admin')->user();
        abort_unless($admin && ($admin->isSuperAdmin() || $admin->canAccessLogisticsModule('settings')), 403);
    }
}
