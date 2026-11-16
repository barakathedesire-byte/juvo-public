<?php

namespace App\Models\Logistics;

use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class CodHandover extends Model
{
    protected $fillable = [
        'reference', 'rider_id', 'amount', 'currency', 'method', 'status',
        'external_reference', 'evidence_path', 'notes',
        'initiated_by_type', 'initiated_by_id', 'initiated_by_name',
        'verified_by_type', 'verified_by_id', 'verified_by_name', 'verified_at',
        'rejection_reason', 'rejected_by_type', 'rejected_by_id', 'rejected_by_name', 'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'verified_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public const METHODS = [
        'cash' => 'Cash',
        'bank' => 'Bank transfer',
        'mobile_money' => 'Mobile money',
    ];

    public const STATUSES = [
        'pending_verification' => 'Awaiting verification',
        'verified' => 'Verified',
        'rejected' => 'Rejected',
        'disputed' => 'Disputed',
    ];

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function allocations()
    {
        return $this->hasMany(CodHandoverAllocation::class);
    }

    public function orders()
    {
        return $this->belongsToMany(\App\Models\Order::class, 'cod_handover_allocations')
            ->withPivot('amount')
            ->withTimestamps();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }
}
