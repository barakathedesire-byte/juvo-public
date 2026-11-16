<?php

namespace App\Services\Support;

use App\Models\SupportSlaPolicy;
use App\Models\SupportTicket;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Resolves the first-response and resolution deadlines for a support ticket
 * from the priority SLA policy and reports whether a ticket has breached it.
 */
class SupportSlaService
{
    /**
     * Fallback minutes by priority when no policy row is configured.
     *
     * @var array<string, array{first: int, resolution: int}>
     */
    public const DEFAULTS = [
        'urgent' => ['first' => 30, 'resolution' => 240],
        'high' => ['first' => 120, 'resolution' => 480],
        'normal' => ['first' => 480, 'resolution' => 1440],
        'low' => ['first' => 1440, 'resolution' => 4320],
    ];

    /**
     * @return array{first_response_due_at: Carbon, resolution_due_at: Carbon}
     */
    public function deadlinesFor(string $priority, ?CarbonInterface $from = null): array
    {
        $from = $from ? Carbon::parse($from) : now();
        $policy = SupportSlaPolicy::where('priority', $priority)->where('is_active', true)->first();

        $first = $policy->first_response_minutes ?? self::DEFAULTS[$priority]['first'] ?? 480;
        $resolution = $policy->resolution_minutes ?? self::DEFAULTS[$priority]['resolution'] ?? 1440;

        return [
            'first_response_due_at' => $from->copy()->addMinutes((int) $first),
            'resolution_due_at' => $from->copy()->addMinutes((int) $resolution),
        ];
    }

    /**
     * Record the first staff response if it has not been recorded yet.
     */
    public function markFirstResponse(SupportTicket $ticket): void
    {
        if (! $ticket->first_responded_at) {
            $ticket->first_responded_at = now();
            $ticket->save();
        }
    }

    /**
     * Whether the ticket has missed its first-response or resolution target.
     */
    public function isBreached(SupportTicket $ticket): bool
    {
        $now = now();

        if ($ticket->status === 'closed') {
            return false;
        }

        if ($ticket->first_response_due_at && ! $ticket->first_responded_at && $ticket->first_response_due_at->lt($now)) {
            return true;
        }

        if ($ticket->resolved_at) {
            return $ticket->resolution_due_at ? $ticket->resolved_at->greaterThan($ticket->resolution_due_at) : false;
        }

        return $ticket->resolution_due_at && $ticket->resolution_due_at->isPast();
    }

    /**
     * Human-readable SLA state: resolved, met, at_risk, breached or on_track.
     */
    public function state(SupportTicket $ticket): string
    {
        if ($ticket->resolved_at) {
            return $this->isBreached($ticket) ? 'breached' : 'met';
        }

        if ($this->isBreached($ticket)) {
            return 'breached';
        }

        $due = $this->nextDeadline($ticket);

        if ($due && now()->diffInMinutes($due, false) <= 60) {
            return 'due';
        }

        return 'on_track';
    }

    public function nextDeadline(SupportTicket $ticket): ?CarbonInterface
    {
        $candidates = array_filter([
            $ticket->first_responded_at ? null : $ticket->first_response_due_at,
            $ticket->resolution_due_at,
        ]);

        return collect($candidates)->sort()->first();
    }
}
