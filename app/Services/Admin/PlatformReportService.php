<?php

namespace App\Services\Admin;

use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Feature;
use App\Models\Order;
use App\Models\SupportTicket;
use App\Models\Vendor;
use App\Services\Sales\SalesAttributionService;
use App\Services\Support\SupportSlaService;
use Illuminate\Support\Carbon;

/**
 * Cross-module roll-up for the admin Command Center. Pulls a single, consistent
 * snapshot of commerce, finance, support, sales and governance signals so staff
 * land on one page instead of visiting each module.
 */
class PlatformReportService
{
    public function __construct(
        protected FinanceReportService $finance,
        protected SupportSlaService $sla,
        protected SalesAttributionService $sales,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function overview(?Carbon $from = null, ?Carbon $to = null): array
    {
        return [
            'range' => [
                'from' => $from,
                'to' => $to,
            ],
            'commerce' => $this->commerce($from, $to),
            'finance' => $this->finance->kpis($from, $to),
            'support' => $this->support($from, $to),
            'sales' => $this->sales($from, $to),
            'governance' => $this->governance(),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function commerce(?Carbon $from, ?Carbon $to): array
    {
        $orders = Order::query()->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $count = (clone $orders)->count();
        $revenue = (float) (clone $orders)->whereNotIn('status', ['canceled'])->sum('total_amount');

        return [
            'orders' => $count,
            'revenue' => round($revenue, 2),
            'avg_order' => $count > 0 ? round($revenue / $count, 2) : 0.0,
            'open_orders' => (int) Order::query()->whereNotIn('status', ['completed', 'canceled', 'returned'])->count(),
            'customers' => (int) Customer::query()->when($from, fn ($q) => $q->where('created_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('created_at', '<=', $to))->count(),
            'vendors' => (int) Vendor::query()->count(),
            'pending_vendors' => (int) Vendor::query()->whereIn('status', ['pending', 'inactive'])->count(),
        ];
    }

    /**
     * @return array<string, float|int|null>
     */
    protected function support(?Carbon $from, ?Carbon $to): array
    {
        $tickets = SupportTicket::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $open = (clone $tickets)->whereNotIn('status', ['resolved', 'closed']);

        $avgFirstResponse = SupportTicket::query()
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to))
            ->whereNotNull('first_responded_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_responded_at)) AS avg_minutes')
            ->value('avg_minutes');

        return [
            'total' => (int) (clone $tickets)->count(),
            'open' => (int) (clone $open)->count(),
            'unassigned' => (int) (clone $open)->whereNull('assigned_to')->count(),
            'breached' => (int) (clone $open)
                ->where(function ($q) {
                    $q->where(fn ($w) => $w->whereNotNull('first_response_due_at')
                        ->whereNull('first_responded_at')
                        ->where('first_response_due_at', '<', now()))
                        ->orWhere(fn ($w) => $w->whereNull('resolved_at')
                            ->whereNotNull('resolution_due_at')
                            ->where('resolution_due_at', '<', now()));
                })
                ->count(),
            'escalated' => (int) (clone $open)->whereNotNull('escalated_at')->count(),
            'resolved' => (int) SupportTicket::query()
                ->when($from, fn ($q) => $q->where('resolved_at', '>=', $from))
                ->when($to, fn ($q) => $q->where('resolved_at', '<=', $to))
                ->whereNotNull('resolved_at')->count(),
            'avg_first_response_minutes' => $avgFirstResponse === null ? null : round((float) $avgFirstResponse, 1),
        ];
    }

    /**
     * @return array<string, float|int>
     */
    protected function sales(?Carbon $from, ?Carbon $to): array
    {
        $summary = $this->sales->summary($from, $to);
        $leaderboard = $this->sales->leaderboard($from, $to);

        $summary['top_rep'] = optional($leaderboard->firstWhere('attributed_total', '>', 0))->only(['name', 'attributed_total', 'target_attainment']);

        return $summary;
    }

    /**
     * @return array<string, int>
     */
    protected function governance(): array
    {
        return [
            'pending_approvals' => (int) ApprovalRequest::query()->open()->count(),
            'audit_events_24h' => (int) AuditLog::query()->where('created_at', '>=', now()->subDay())->count(),
            'features_total' => (int) Feature::query()->count(),
            'features_enabled' => (int) Feature::query()->where('is_enabled', true)->count(),
        ];
    }

    /**
     * Latest activity feed across the governance surfaces (approvals + audit).
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    public function activity(int $limit = 12)
    {
        return AuditLog::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'subject' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                'actor_id' => $log->actor_id,
                'created_at' => $log->created_at,
            ]);
    }
}
