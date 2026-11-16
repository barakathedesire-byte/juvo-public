<?php

namespace App\Models\Rider;

use Illuminate\Database\Eloquent\Model;

class RiderPayout extends Model
{
    protected $fillable = [
        'reference', 'rider_id', 'amount', 'currency', 'status', 'method',
        'account_name', 'account_number', 'bank_name', 'provider', 'provider_reference',
        'notes', 'failure_reason',
        'initiated_by_type', 'initiated_by_id', 'initiated_by_name',
        'approved_by_type', 'approved_by_id', 'approved_by_name', 'approved_at',
        'completed_by_type', 'completed_by_id', 'completed_by_name', 'completed_at',
        'failed_by_type', 'failed_by_id', 'failed_by_name', 'failed_at',
        'reversed_by_type', 'reversed_by_id', 'reversed_by_name', 'reversed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'account_number' => 'encrypted',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
        'failed_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    protected $hidden = ['account_number'];

    public const METHODS = [
        'bank' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
    ];

    public const STATUSES = [
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'processing' => 'Processing',
        'completed' => 'Completed',
        'failed' => 'Failed',
        'rejected' => 'Rejected',
        'reversed' => 'Reversed',
    ];

    public const OPEN_STATUSES = ['pending_approval', 'approved', 'processing'];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function ledgerEntry()
    {
        return $this->hasOne(RiderLedgerEntry::class, 'rider_payout_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN_STATUSES, true);
    }
}
