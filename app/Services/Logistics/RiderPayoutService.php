<?php

namespace App\Services\Logistics;

use App\Models\Order;
use App\Models\Rider\Rider;
use App\Models\Rider\RiderLedgerEntry;
use App\Models\Rider\RiderPayout;
use App\Services\Admin\AuditLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RiderPayoutService
{
    public function overview(Rider $rider): array
    {
        $credits = (float) RiderLedgerEntry::where('rider_id', $rider->id)
            ->where('direction', 'credit')
            ->where('status', 'available')
            ->sum('amount');

        $debits = (float) RiderLedgerEntry::where('rider_id', $rider->id)
            ->where('direction', 'debit')
            ->whereIn('status', ['available', 'pending', 'paid'])
            ->sum('amount');

        $grossEarnings = (float) RiderLedgerEntry::where('rider_id', $rider->id)
            ->where('direction', 'credit')
            ->whereIn('type', ['delivery_earning', 'bonus', 'adjustment'])
            ->where('status', '!=', 'reversed')
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        $deductions = (float) RiderLedgerEntry::where('rider_id', $rider->id)
            ->where('direction', 'debit')
            ->whereIn('type', ['deduction', 'adjustment'])
            ->whereIn('status', ['available', 'pending', 'paid'])
            ->sum('amount');

        $pendingEarnings = (float) Order::where('assigned_driver_id', $rider->id)
            ->whereIn('logistics_status', ['assigned', 'accepted', 'picked_up', 'in_transit'])
            ->sum('delivery_fee');

        $approved = (float) RiderPayout::where('rider_id', $rider->id)->where('status', 'approved')->sum('amount');
        $completed = (float) RiderPayout::where('rider_id', $rider->id)->where('status', 'completed')->sum('amount');
        $failed = (float) RiderPayout::where('rider_id', $rider->id)->whereIn('status', ['failed', 'rejected', 'reversed'])->sum('amount');
        $open = (float) RiderPayout::where('rider_id', $rider->id)->whereIn('status', RiderPayout::OPEN_STATUSES)->sum('amount');

        return [
            'gross_earnings' => $grossEarnings,
            'pending_earnings' => $pendingEarnings,
            'available' => max(0.0, $credits - $debits),
            'approved' => $approved,
            'completed' => $completed,
            'failed' => $failed,
            'deductions' => $deductions,
            'open' => $open,
        ];
    }

    public function availableBalance(Rider $rider): float
    {
        return $this->overview($rider)['available'];
    }

    public function recordDeliveryEarning(Order $order, ?Model $actor = null): ?RiderLedgerEntry
    {
        if (! $order->assigned_driver_id || (float) $order->delivery_fee <= 0) {
            return null;
        }

        $sourceKey = 'order:'.$order->id.':delivery_earning';

        return DB::transaction(function () use ($order, $actor, $sourceKey) {
            $existing = RiderLedgerEntry::where('source_key', $sourceKey)->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $entry = RiderLedgerEntry::create([
                'reference' => 'ERN-'.$order->id.'-'.strtoupper(Str::random(5)),
                'rider_id' => $order->assigned_driver_id,
                'order_id' => $order->id,
                'type' => 'delivery_earning',
                'direction' => 'credit',
                'amount' => $order->delivery_fee,
                'currency' => strtoupper($order->currency ?: 'TZS'),
                'status' => 'available',
                'description' => 'Delivery earnings for order #'.$order->id,
                'source_key' => $sourceKey,
                'created_by_type' => $actor ? get_class($actor) : null,
                'created_by_id' => $actor?->getKey(),
                'created_by_name' => $this->actorName($actor),
                'occurred_at' => $order->delivered_at ?: now(),
            ]);

            AuditLogger::record('logistics.rider.earning_recorded', $entry, [], [
                'order_id' => $order->id,
                'rider_id' => $order->assigned_driver_id,
                'amount' => $entry->amount,
            ], null, $actor);

            return $entry;
        });
    }

    public function reverseDeliveryEarning(Order $order, ?Model $actor = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($order, $actor, $reason) {
            $entry = RiderLedgerEntry::where('source_key', 'order:'.$order->id.':delivery_earning')
                ->lockForUpdate()
                ->first();

            if (! $entry || $entry->status === 'reversed' || $entry->status === 'paid') {
                return;
            }

            $entry->forceFill(['status' => 'reversed'])->save();

            AuditLogger::record('logistics.rider.earning_reversed', $entry, ['status' => $entry->getOriginal('status')], ['status' => 'reversed'], $reason, $actor);
        });
    }

    public function createPayout(Rider $rider, array $data, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($rider, $data, $actor) {
            $lockedRider = Rider::whereKey($rider->id)->lockForUpdate()->firstOrFail();
            $amount = round((float) $data['amount'], 2);
            $balance = $this->availableBalance($lockedRider);

            if ($amount <= 0) {
                throw ValidationException::withMessages(['amount' => 'Payout amount must be greater than zero.']);
            }

            if ($amount > $balance + 0.009) {
                throw ValidationException::withMessages([
                    'amount' => 'The payout exceeds the rider\'s available balance of '.number_format($balance, 2).'.',
                ]);
            }

            $payout = RiderPayout::create([
                'reference' => 'PAY-'.now()->format('Ymd').'-'.strtoupper(Str::random(6)),
                'rider_id' => $lockedRider->id,
                'amount' => $amount,
                'currency' => strtoupper($data['currency'] ?? 'TZS'),
                'status' => 'pending_approval',
                'method' => $data['method'],
                'account_name' => $data['account_name'] ?? $lockedRider->bank_account_name,
                'account_number' => $data['account_number'] ?? $lockedRider->bank_account_number,
                'bank_name' => $data['bank_name'] ?? $lockedRider->bank_name,
                'provider' => $data['provider'] ?? null,
                'notes' => $data['notes'] ?? null,
                'initiated_by_type' => get_class($actor),
                'initiated_by_id' => $actor->getKey(),
                'initiated_by_name' => $this->actorName($actor),
            ]);

            RiderLedgerEntry::create([
                'reference' => 'LED-'.$payout->reference,
                'rider_id' => $payout->rider_id,
                'rider_payout_id' => $payout->id,
                'type' => 'payout',
                'direction' => 'debit',
                'amount' => $payout->amount,
                'currency' => $payout->currency,
                'status' => 'pending',
                'description' => 'Payout '.$payout->reference.' pending settlement',
                'source_key' => 'payout:'.$payout->id,
                'created_by_type' => get_class($actor),
                'created_by_id' => $actor->getKey(),
                'created_by_name' => $this->actorName($actor),
                'occurred_at' => now(),
            ]);

            AuditLogger::record('logistics.payout.created', $payout, [], $payout->toArray(), null, $actor);

            return $payout;
        });
    }

    public function approve(RiderPayout $payout, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($payout, $actor) {
            $locked = RiderPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, ['pending_approval']);
            $this->assertSeparateActor($locked, $actor, 'approve');
            $this->assertApprovalLimit($locked, $actor);

            $locked->forceFill([
                'status' => 'approved',
                'approved_by_type' => get_class($actor),
                'approved_by_id' => $actor->getKey(),
                'approved_by_name' => $this->actorName($actor),
                'approved_at' => now(),
            ])->save();

            AuditLogger::record('logistics.payout.approved', $locked, ['status' => 'pending_approval'], ['status' => 'approved'], null, $actor);

            return $locked;
        });
    }

    public function complete(RiderPayout $payout, array $data, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($payout, $data, $actor) {
            $locked = RiderPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, ['approved', 'processing']);
            $this->assertSeparateActor($locked, $actor, 'complete');

            $reference = trim((string) ($data['provider_reference'] ?? ''));
            if ($reference === '') {
                throw ValidationException::withMessages([
                    'provider_reference' => 'A verified provider or bank transaction reference is required.',
                ]);
            }

            if (! ($data['confirmed'] ?? false)) {
                throw ValidationException::withMessages([
                    'confirmed' => 'Confirm that the provider or authorized bank transfer has been verified.',
                ]);
            }

            $duplicate = RiderPayout::where('provider_reference', $reference)
                ->where('status', 'completed')
                ->whereKeyNot($locked->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'provider_reference' => 'This provider reference has already been used for a completed payout.',
                ]);
            }

            $locked->forceFill([
                'status' => 'completed',
                'provider_reference' => $reference,
                'provider' => $data['provider'] ?? $locked->provider,
                'completed_by_type' => get_class($actor),
                'completed_by_id' => $actor->getKey(),
                'completed_by_name' => $this->actorName($actor),
                'completed_at' => now(),
            ])->save();

            RiderLedgerEntry::where('rider_payout_id', $locked->id)->update([
                'status' => 'paid',
                'description' => 'Payout '.$locked->reference.' completed',
                'updated_at' => now(),
            ]);

            AuditLogger::record('logistics.payout.completed', $locked, ['status' => 'approved'], [
                'status' => 'completed',
                'provider_reference' => $reference,
            ], null, $actor);

            return $locked;
        });
    }

    public function reject(RiderPayout $payout, string $reason, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($payout, $reason, $actor) {
            $locked = RiderPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, ['pending_approval']);

            $locked->forceFill([
                'status' => 'rejected',
                'failure_reason' => $reason,
                'failed_by_type' => get_class($actor),
                'failed_by_id' => $actor->getKey(),
                'failed_by_name' => $this->actorName($actor),
                'failed_at' => now(),
            ])->save();

            RiderLedgerEntry::where('rider_payout_id', $locked->id)->update([
                'status' => 'reversed',
                'updated_at' => now(),
            ]);

            AuditLogger::record('logistics.payout.rejected', $locked, ['status' => 'pending_approval'], ['status' => 'rejected'], $reason, $actor);

            return $locked;
        });
    }

    public function fail(RiderPayout $payout, string $reason, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($payout, $reason, $actor) {
            $locked = RiderPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, ['approved', 'processing']);

            $locked->forceFill([
                'status' => 'failed',
                'failure_reason' => $reason,
                'failed_by_type' => get_class($actor),
                'failed_by_id' => $actor->getKey(),
                'failed_by_name' => $this->actorName($actor),
                'failed_at' => now(),
            ])->save();

            RiderLedgerEntry::where('rider_payout_id', $locked->id)->update([
                'status' => 'reversed',
                'updated_at' => now(),
            ]);

            AuditLogger::record('logistics.payout.failed', $locked, ['status' => 'approved'], ['status' => 'failed'], $reason, $actor);

            return $locked;
        });
    }

    public function reverse(RiderPayout $payout, string $reason, Model $actor): RiderPayout
    {
        return DB::transaction(function () use ($payout, $reason, $actor) {
            $locked = RiderPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            $this->assertStatus($locked, ['completed']);

            $locked->forceFill([
                'status' => 'reversed',
                'failure_reason' => $reason,
                'reversed_by_type' => get_class($actor),
                'reversed_by_id' => $actor->getKey(),
                'reversed_by_name' => $this->actorName($actor),
                'reversed_at' => now(),
            ])->save();

            RiderLedgerEntry::where('rider_payout_id', $locked->id)->update([
                'status' => 'reversed',
                'updated_at' => now(),
            ]);

            AuditLogger::record('logistics.payout.reversed', $locked, ['status' => 'completed'], ['status' => 'reversed'], $reason, $actor);

            return $locked;
        });
    }

    private function assertStatus(RiderPayout $payout, array $statuses): void
    {
        if (! in_array($payout->status, $statuses, true)) {
            throw ValidationException::withMessages([
                'payout' => 'This payout cannot be changed while it is "'.$payout->statusLabel().'".',
            ]);
        }
    }

    private function assertSeparateActor(RiderPayout $payout, Model $actor, string $action): void
    {
        if ((int) $payout->initiated_by_id === (int) $actor->getKey()
            && $payout->initiated_by_type === get_class($actor)) {
            throw ValidationException::withMessages([
                'payout' => 'The person who initiated a payout cannot '.$action.' it. A different finance approver is required.',
            ]);
        }
    }

    private function assertApprovalLimit(RiderPayout $payout, Model $actor): void
    {
        $limit = (float) config('juvo-logistics.finance.payout_approval_limit', 0);

        if ($limit <= 0 || $actor->isSuperAdmin()) {
            return;
        }

        if ((float) $payout->amount > $limit) {
            throw ValidationException::withMessages([
                'payout' => 'This payout exceeds your approval limit. A super administrator must approve it.',
            ]);
        }
    }

    private function actorName(?Model $actor): string
    {
        if (! $actor) {
            return 'System';
        }

        return $actor->name ?? $actor->full_name ?? 'User #'.$actor->getKey();
    }
}
