<?php

namespace App\Models\Logistics;

use App\Models\Order;
use App\Models\Rider\Rider;
use Illuminate\Database\Eloquent\Model;

class DeliveryEvent extends Model
{
    protected $fillable = [
        'order_id', 'rider_id', 'type', 'from_status', 'to_status',
        'actor_type', 'actor_id', 'actor_name', 'note', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public const TYPE_ASSIGNED = 'assigned';
    public const TYPE_REASSIGNED = 'reassigned';
    public const TYPE_UNASSIGNED = 'unassigned';
    public const TYPE_ACCEPTED = 'accepted';
    public const TYPE_DECLINED = 'declined';
    public const TYPE_ARRIVED_PICKUP = 'arrived_pickup';
    public const TYPE_PICKED_UP = 'picked_up';
    public const TYPE_IN_TRANSIT = 'in_transit';
    public const TYPE_ARRIVED_DROPOFF = 'arrived_dropoff';
    public const TYPE_DELIVERED = 'delivered';
    public const TYPE_FAILED = 'failed';
    public const TYPE_RESCHEDULED = 'rescheduled';
    public const TYPE_RETURNED = 'returned';
    public const TYPE_CANCELLED = 'cancelled';
    public const TYPE_COD_COLLECTED = 'cod_collected';
    public const TYPE_COD_REMITTED = 'cod_remitted';
    public const TYPE_NOTE = 'note';

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function rider()
    {
        return $this->belongsTo(Rider::class);
    }
}
