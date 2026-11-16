<?php

namespace App\Services\Logistics;

use App\Models\Logistics\Rider\Rider;
use App\Models\Logistics\RiskCase;
use App\Models\Order;
use App\Models\Rider\Rider as RiderModel;
use Illuminate\Support\Str;

class FraudDetectionService
{
    public function scan(): array
    {
        $created = [];
        $created = array_merge($created, $this->detectCodDiscrepancies());
        $created = array_merge($created, $this->detectPayoutDuplicates());
        $created = array_merge($created, $this->detectDeliveryInconsistencies());
        $created = array_merge($created, $this->detectDuplicateHandovers());

        return $created;
    }

    protected function detectCodDiscrepancies(): array
    {
        $created = [];
        $orders = Order::whereNotNull('cod_collected_at')
            ->whereNotNull('cod_remitted_at')
            ->get();

        foreach ($orders as $order) {
            $collected = (float) ($order->cod_amount ?? $order->total_amount);
            $remitted = (float) ($order->cod_remitted_amount ?? 0);
            if ($remitted > $collected + 0.01) {
                $created[] = $this->createCase(
                    'cod_over_remittance',
                    'high',
                    Order::class,
                    $order->id,
                    'COD remittance exceeds collected amount for order '.$order->id,
                    'Remitted '.number_format($remitted, 2).' exceeds collected '.number_format($collected, 2).'.',
                    80,
                    ['collected' => $collected, 'remitted' => $remitted]
                );
            }
        }

        // Repeated unresolved COD discrepancies by rider
        $riders = RiderModel::all();
        foreach ($riders as $rider) {
            $discrepant = Order::where('assigned_driver_id', $rider->id)
                ->whereNotNull('cod_collected_at')
                ->whereRaw('(COALESCE(cod_amount, total_amount) - COALESCE(cod_remitted_amount, 0)) < 0')
                ->count();
            if ($discrepant >= 3) {
                $created[] = $this->createCase(
                    'rider_cod_pattern',
                    'moderate',
                    RiderModel::class,
                    $rider->id,
                    'Repeated COD reconciliation discrepancies for rider '.$rider->full_name,
                    $discrepant.' orders have negative outstanding COD balances.',
                    65,
                    ['discrepant_orders' => $discrepant]
                );
            }
        }

        return array_filter($created);
    }

    protected function detectPayoutDuplicates(): array
    {
        $created = [];
        $duplicates = \App\Models\Rider\RiderPayout::select('provider_reference')
            ->whereNotNull('provider_reference')
            ->groupBy('provider_reference')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('provider_reference');

        foreach ($duplicates as $reference) {
            $payouts = \App\Models\Rider\RiderPayout::where('provider_reference', $reference)->get();
            $created[] = $this->createCase(
                'duplicate_payout_reference',
                'high',
                \App\Models\Rider\RiderPayout::class,
                $payouts->first()->id,
                'Duplicate payout provider reference: '.$reference,
                'Multiple payouts share provider reference '.$reference.'.',
                85,
                ['payout_ids' => $payouts->pluck('id')->all()]
            );
        }

        return array_filter($created);
    }

    protected function detectDeliveryInconsistencies(): array
    {
        $created = [];
        $orders = Order::where('logistics_status', 'delivered')
            ->whereNull('picked_up_at')
            ->limit(100)
            ->get();

        foreach ($orders as $order) {
            $created[] = $this->createCase(
                'delivery_without_pickup',
                'moderate',
                Order::class,
                $order->id,
                'Delivery recorded without pickup timestamp for order '.$order->id,
                'Order is delivered but picked_up_at is missing.',
                60,
                ['delivered_at' => optional($order->delivered_at)->toDateTimeString()]
            );
        }

        $duplicateEvents = \App\Models\Logistics\DeliveryEvent::select('order_id', 'type')
            ->groupBy('order_id', 'type')
            ->havingRaw('COUNT(*) > 3')
            ->get();

        foreach ($duplicateEvents as $row) {
            $created[] = $this->createCase(
                'repeated_delivery_event',
                'low',
                Order::class,
                $row->order_id,
                'Repeated delivery events for order '.$row->order_id,
                'Event type '.$row->type.' occurred more than three times.',
                40,
                ['type' => $row->type]
            );
        }

        return array_filter($created);
    }

    protected function detectDuplicateHandovers(): array
    {
        $created = [];
        $duplicates = \App\Models\Logistics\CodHandover::select('reference')
            ->groupBy('reference')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('reference');

        foreach ($duplicates as $reference) {
            $handovers = \App\Models\Logistics\CodHandover::where('reference', $reference)->get();
            $created[] = $this->createCase(
                'duplicate_cod_handover',
                'high',
                \App\Models\Logistics\CodHandover::class,
                $handovers->first()->id,
                'Duplicate COD handover reference: '.$reference,
                'Multiple COD handovers share reference '.$reference.'.',
                85,
                ['handover_ids' => $handovers->pluck('id')->all()]
            );
        }

        return array_filter($created);
    }

    protected function createCase(string $type, string $severity, string $subjectType, int $subjectId, string $title, string $description, int $score, array $factors): ?RiskCase
    {
        $existing = RiskCase::where('anomaly_type', $type)
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->whereIn('status', ['new', 'under_review', 'investigation_in_progress', 'awaiting_evidence', 'escalated'])
            ->first();

        if ($existing) {
            return null;
        }

        do {
            $number = 'RISK-'.now()->format('Ymd').'-'.strtoupper(Str::random(5));
        } while (RiskCase::where('case_number', $number)->exists());

        return RiskCase::create([
            'case_number' => $number,
            'anomaly_type' => $type,
            'severity' => $severity,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'title' => $title,
            'description' => $description,
            'risk_score' => $score,
            'factors' => $factors,
            'status' => 'new',
        ]);
    }
}
