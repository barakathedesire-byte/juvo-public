<?php

namespace App\Services\Intelligence;

use App\Models\SupportCannedResponse;
use App\Models\SupportTicket;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Algorithm 10 — Support Triage & Knowledge.
 *
 * Scores a ticket for urgency, proposes a priority and a queue, and surfaces
 * the canned response and published page most likely to resolve it. SLA risk
 * is measured against the ticket's own due dates rather than a blanket clock,
 * so a breached first-response and a breached resolution are told apart.
 */
class SupportAssistService
{
    public const ALGORITHM_KEY = 'support_assist';

    public const QUEUE_BY_CATEGORY = [
        'delivery' => 'logistics',
        'shipping' => 'logistics',
        'payment' => 'finance',
        'refund' => 'finance',
        'billing' => 'finance',
        'product' => 'catalogue',
        'inventory' => 'catalogue',
        'vendor' => 'marketplace',
        'account' => 'account',
    ];

    public function __construct(protected AlgorithmRegistry $registry) {}

    public function isEnabled(): bool
    {
        return $this->registry->isActive(self::ALGORITHM_KEY);
    }

    public function config(): array
    {
        return $this->registry->configFor(self::ALGORITHM_KEY);
    }

    /**
     * @return array{
     *     ticket_id: int,
     *     score: int,
     *     suggested_priority: string,
     *     suggested_queue: string,
     *     sla_risk: string,
     *     signals: array<int, array{name: string, points: int, detail: string}>,
     *     knowledge: array{canned_response_id: int|null, page_slug: string|null}
     * }
     */
    public function triage(SupportTicket $ticket): array
    {
        $config = $this->config();

        $weights = array_merge([
            'sla_overdue' => 30,
            'escalation' => 20,
            'reopened' => 15,
            'priority' => 15,
            'age' => 10,
            'value_at_risk' => 10,
        ], (array) ($config['weights'] ?? []));

        $signals = [];
        $score = 0;
        $now = Carbon::now();

        $firstDue = $ticket->first_response_due_at ? Carbon::parse($ticket->first_response_due_at) : null;
        $resolutionDue = $ticket->resolution_due_at ? Carbon::parse($ticket->resolution_due_at) : null;
        $overdueFirst = $firstDue && ! $ticket->first_responded_at && $now->greaterThan($firstDue);
        $overdueResolution = $resolutionDue && ! $ticket->resolved_at && $now->greaterThan($resolutionDue);

        if ($overdueFirst || $overdueResolution) {
            $points = (int) $weights['sla_overdue'];
            $score += $points;
            $signals[] = [
                'name' => 'sla_overdue',
                'points' => $points,
                'detail' => $overdueFirst ? 'First response SLA has been breached.' : 'Resolution SLA has been breached.',
            ];
        }

        $escalation = (int) $ticket->escalation_level;
        if ($escalation > 0) {
            $points = (int) $weights['escalation'];
            $score += $points;
            $signals[] = [
                'name' => 'escalation',
                'points' => $points,
                'detail' => "Ticket is at escalation level {$escalation}.",
            ];
        }

        $reopened = (int) $ticket->reopened_count;
        if ($reopened > 0) {
            $points = (int) $weights['reopened'];
            $score += $points;
            $signals[] = [
                'name' => 'reopened',
                'points' => $points,
                'detail' => "Ticket has been reopened {$reopened} time(s).",
            ];
        }

        $priorityPoints = ['urgent' => 1.0, 'high' => 0.7, 'medium' => 0.4, 'low' => 0.1];
        $priorityFactor = $priorityPoints[strtolower((string) $ticket->priority)] ?? 0.3;
        $priorityScore = (int) round($weights['priority'] * $priorityFactor);
        if ($priorityScore > 0) {
            $score += $priorityScore;
            $signals[] = [
                'name' => 'priority',
                'points' => $priorityScore,
                'detail' => "Declared priority is '{$ticket->priority}'.",
            ];
        }

        $ageHours = (int) $ticket->created_at?->diffInHours($now);
        $ageLimit = max(1, (int) ($config['age_weight_hours'] ?? 48));
        if ($ageHours > $ageLimit) {
            $points = (int) $weights['age'];
            $score += $points;
            $signals[] = [
                'name' => 'age',
                'points' => $points,
                'detail' => "Ticket has been open for {$ageHours} hours.",
            ];
        }

        $orderValue = $this->linkedOrderValue($ticket);
        $valueThreshold = (float) ($config['high_value_order_threshold'] ?? 500);
        if ($orderValue > 0 && $valueThreshold > 0 && $orderValue >= $valueThreshold) {
            $points = (int) $weights['value_at_risk'];
            $score += $points;
            $signals[] = [
                'name' => 'value_at_risk',
                'points' => $points,
                'detail' => 'Linked order is worth '.number_format($orderValue, 2).'.',
            ];
        }

        $score = max(0, min(100, $score));

        return [
            'ticket_id' => $ticket->id,
            'score' => $score,
            'suggested_priority' => $this->priorityFromScore($score),
            'suggested_queue' => self::QUEUE_BY_CATEGORY[strtolower((string) $ticket->category)] ?? 'general',
            'sla_risk' => $this->slaRisk($overdueFirst, $overdueResolution, $resolutionDue, $now),
            'signals' => $signals,
            'knowledge' => $this->knowledge($ticket),
        ];
    }

    /**
     * Open backlog scored for urgency, worst first.
     */
    public function backlog(int $limit = 25): Collection
    {
        return SupportTicket::query()
            ->whereNotIn('status', ['resolved', 'closed'])
            ->orderBy('created_at')
            ->limit(max(1, $limit) * 3)
            ->get()
            ->map(fn (SupportTicket $ticket) => $this->triage($ticket))
            ->sortByDesc('score')
            ->take(max(1, $limit))
            ->values();
    }

    protected function priorityFromScore(int $score): string
    {
        return match (true) {
            $score >= 75 => 'urgent',
            $score >= 50 => 'high',
            $score >= 25 => 'medium',
            default => 'low',
        };
    }

    protected function slaRisk(bool $overdueFirst, bool $overdueResolution, ?Carbon $resolutionDue, Carbon $now): string
    {
        if ($overdueResolution) {
            return 'resolution_breached';
        }

        if ($overdueFirst) {
            return 'first_response_breached';
        }

        if ($resolutionDue && $resolutionDue->diffInHours($now) <= 24) {
            return 'at_risk';
        }

        return 'on_track';
    }

    protected function linkedOrderValue(SupportTicket $ticket): float
    {
        if (! $ticket->customer_id) {
            return 0.0;
        }

        return (float) DB::table('orders')
            ->where('customer_id', $ticket->customer_id)
            ->orderByDesc('created_at')
            ->value('total_amount');
    }

    /**
     * @return array{canned_response_id: int|null, page_slug: string|null}
     */
    protected function knowledge(SupportTicket $ticket): array
    {
        $haystack = Str::lower(trim(implode(' ', array_filter([
            (string) $ticket->subject,
            (string) $ticket->category,
        ]))));

        $cannedId = null;
        $pageSlug = null;

        if ($haystack !== '') {
            $tokens = collect(preg_split('/\W+/', $haystack))
                ->filter(fn (string $token) => mb_strlen($token) >= 4)
                ->values();

            if ($tokens->isNotEmpty()) {
                $canned = SupportCannedResponse::where('is_active', 1)
                    ->orderByDesc('id')
                    ->get(['id', 'title', 'body', 'category'])
                    ->map(fn ($response) => [
                        'id' => $response->id,
                        'hits' => $this->matches($haystack, $tokens, $response->title.' '.$response->body.' '.$response->category),
                    ])
                    ->sortByDesc('hits')
                    ->first();

                if ($canned && $canned['hits'] > 0) {
                    $cannedId = $canned['id'];
                }

                $page = DB::table('pages')
                    ->where('status', 'published')
                    ->orderByDesc('id')
                    ->get(['id', 'slug', 'seo_title', 'seo_description'])
                    ->map(fn ($row) => [
                        'slug' => $row->slug,
                        'hits' => $this->matches($haystack, $tokens, ($row->seo_title ?? '').' '.($row->seo_description ?? '').' '.$row->slug),
                    ])
                    ->sortByDesc('hits')
                    ->first();

                if ($page && $page['hits'] > 0) {
                    $pageSlug = $page['slug'];
                }
            }
        }

        return ['canned_response_id' => $cannedId, 'page_slug' => $pageSlug];
    }

    /**
     * @param  Collection<int, string>  $tokens
     */
    protected function matches(string $haystack, Collection $tokens, string $target): int
    {
        $target = Str::lower($target);

        return $tokens->filter(fn (string $token) => str_contains($target, $token))->count();
    }
}
