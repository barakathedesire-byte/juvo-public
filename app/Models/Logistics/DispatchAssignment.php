<?php

namespace App\Models\Logistics;

use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class DispatchAssignment extends Model
{
    protected $table = 'logistics_dispatch_assignments';

    protected $fillable = [
        'order_id', 'rider_id', 'assigned_by', 'assignment_type', 'score',
        'factors', 'status', 'accepted_at', 'rejected_at', 'timed_out_at',
        'reassigned_from_id', 'reason',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'factors' => 'array',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
        'timed_out_at' => 'datetime',
    ];

    public function order() { return $this->belongsTo(Order::class); }
    public function rider() { return $this->belongsTo(Rider::class); }
    public function assignedBy() { return $this->belongsTo(\App\Models\Logistics\LogisticsAdmin::class, 'assigned_by'); }
}
