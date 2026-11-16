<?php

namespace App\Models\Logistics;

use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class CodAdjustment extends Model
{
    protected $fillable = [
        'reference', 'order_id', 'rider_id', 'type', 'direction', 'amount', 'currency',
        'status', 'reason', 'evidence_path',
        'created_by_type', 'created_by_id', 'created_by_name',
        'approved_by_type', 'approved_by_id', 'approved_by_name', 'approved_at',
        'rejection_reason', 'rejected_by_type', 'rejected_by_id', 'rejected_by_name', 'rejected_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    public const TYPES = [
        'discrepancy' => 'Discrepancy',
        'write_off' => 'Write-off',
        'refund' => 'Refund',
        'correction' => 'Correction',
    ];

    public const STATUSES = [
        'pending_approval' => 'Pending approval',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }
}
