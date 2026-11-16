<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    // Define the table associated with the model
    protected $table = 'orders';

    // Define the fields that can be mass-assigned
    protected $fillable = [
        'vendor_id',
        'customer_id',
        'guest_email',
        'is_guest',
        'total_amount',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'juvo_funded_discount',
        'vendor_funded_discount',
        'pricing_breakdown',
        'coupon_code',
        'delivery_fee',
        'shipping_method',
        'tracking_number',
        'carrier',
        'payment_status',
        'payment_method',
        'currency',
        'commission_total',
        'net_vendor_amount',
        'note',
        'status',
        'fulfillment_type',
        'pickup_location_id',
        'pickup_location_name',
        'fulfilled_at',
        'delivered_at',
        'pickup_ready_at',
        'rider_arrived_at',
        'canceled_at',
        'cancel_reason',
        // Juvo Logistics fields
        'logistics_tracking_id',
        'logistics_carrier',
        'logistics_status',
        'logistics_priority',
        'logistics_data',
        'assigned_driver_id',
        'driver_name',
        'driver_phone',
        'driver_lat',
        'driver_lng',
        'picked_up_at',
        'pod_photo_url',
        'pod_signature_url',
        'pod_otp_verified',
        'pod_delivered_at',
        'assigned_at',
        'accepted_at',
        'failed_at',
        'failed_reason',
        'failure_note',
        'delivery_attempts',
        'scheduled_for',
        'returned_at',
        'pod_receiver_name',
        'pod_notes',
        'cod_amount',
        'cod_collected_at',
        'cod_remitted_at',
        'cod_remittance_ref',
        'cod_remitted_amount',
    ];

    protected $casts = [
        'fulfilled_at' => 'datetime',
        'delivered_at' => 'datetime',
        'pickup_ready_at' => 'datetime',
        'rider_arrived_at' => 'datetime',
        'canceled_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'juvo_funded_discount' => 'decimal:2',
        'vendor_funded_discount' => 'decimal:2',
        'pricing_breakdown' => 'array',
        'delivery_fee' => 'decimal:2',
        'commission_total' => 'decimal:2',
        'net_vendor_amount' => 'decimal:2',
        'is_guest' => 'boolean',
        // Juvo Logistics casts
        'logistics_data' => 'array',
        'driver_lat' => 'decimal:8',
        'driver_lng' => 'decimal:8',
        'pod_otp_verified' => 'boolean',
        'picked_up_at' => 'datetime',
        'pod_delivered_at' => 'datetime',
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'failed_at' => 'datetime',
        'returned_at' => 'datetime',
        'scheduled_for' => 'date',
        'delivery_attempts' => 'integer',
        'cod_amount' => 'decimal:2',
        'cod_collected_at' => 'datetime',
        'cod_remitted_at' => 'datetime',
        'cod_remitted_amount' => 'decimal:2',
    ];

    /**
     * Delivery lifecycle statuses used by the operations portal.
     */
    public const LOGISTICS_STATUSES = [
        'pending',
        'created',
        'assigned',
        'accepted',
        'picked_up',
        'in_transit',
        'delivered',
        'failed',
        'returned',
        'cancelled',
    ];

    public const LOGISTICS_STATUS_LABELS = [
        'pending' => 'Awaiting dispatch',
        'created' => 'Requested',
        'assigned' => 'Awaiting rider acceptance',
        'accepted' => 'Rider accepted',
        'picked_up' => 'Picked up',
        'in_transit' => 'Out for delivery',
        'delivered' => 'Delivered',
        'failed' => 'Failed attempt',
        'returned' => 'Returned to sender',
        'cancelled' => 'Cancelled',
    ];

    public const LOGISTICS_TRANSITIONS = [
        'pending' => ['created', 'assigned', 'cancelled'],
        'created' => ['assigned', 'cancelled'],
        'assigned' => ['accepted', 'picked_up', 'failed', 'cancelled', 'pending'],
        'accepted' => ['picked_up', 'failed', 'cancelled', 'assigned'],
        'picked_up' => ['in_transit', 'delivered', 'failed'],
        'in_transit' => ['delivered', 'failed'],
        'failed' => ['assigned', 'returned', 'cancelled'],
        'delivered' => ['returned'],
        'returned' => [],
        'cancelled' => [],
    ];

    public const FAILURE_REASONS = [
        'customer_unavailable' => 'Customer unavailable',
        'customer_refused' => 'Customer refused delivery',
        'wrong_address' => 'Wrong or incomplete address',
        'no_answer' => 'No answer at drop-off',
        'payment_issue' => 'Payment could not be collected',
        'damaged' => 'Item damaged in transit',
        'vehicle_issue' => 'Rider vehicle breakdown',
        'access_issue' => 'Could not access location',
        'other' => 'Other',
    ];

    /**
     * Allowed order status transitions.
     */
    public const STATUS_TRANSITIONS = [
        'pending' => ['accepted', 'processing', 'canceled'],
        'accepted' => ['processing', 'packed', 'ready_for_pickup', 'canceled'],
        'processing' => ['packed', 'ready_for_pickup', 'dispatched', 'canceled'],
        'packed' => ['dispatched', 'ready_for_pickup', 'canceled'],
        'ready_for_pickup' => ['dispatched', 'delivered', 'completed', 'canceled'],
        'dispatched' => ['rider_arrived', 'delivered', 'completed', 'returned'],
        'rider_arrived' => ['delivered', 'completed', 'returned'],
        'delivered' => ['completed', 'returned'],
        'completed' => ['returned'],
        'canceled' => [],
        'returned' => [],
    ];

    /**
     * Customer-facing labels for every status in the lifecycle.
     */
    public const STATUS_LABELS = [
        'pending' => 'Order placed',
        'accepted' => 'Vendor received order',
        'processing' => 'Preparing your order',
        'packed' => 'Packed',
        'dispatched' => 'Shipped',
        'ready_for_pickup' => 'Ready for pickup',
        'rider_arrived' => 'Rider arrived',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'canceled' => 'Canceled',
        'returned' => 'Returned',
    ];

    public const FULFILLMENT_DELIVERY = 'delivery';

    public const FULFILLMENT_PICKUP = 'pickup';

    public function canTransitionTo(string $status): bool
    {
        return in_array($status, self::STATUS_TRANSITIONS[$this->status] ?? [], true);
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->latest();
    }

    public function commissions()
    {
        return $this->hasMany(VendorCommission::class);
    }

    public function transactions()
    {
        return $this->hasMany(VendorTransaction::class);
    }

    // Define the relationship with the Product model (assuming you have a Product model)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function details()
    {
        return $this->hasMany(OrderDetail::class, 'order_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function shippingAddress()
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function returns()
    {
        return $this->hasMany(Refund::class, 'order_id');
    }

    public function isReadyForPickup(): bool
    {
        return $this->status === 'ready_for_pickup';
    }

    public function isPickup(): bool
    {
        return ($this->fulfillment_type ?? self::FULFILLMENT_DELIVERY) === self::FULFILLMENT_PICKUP;
    }

    public function pickupLocation()
    {
        return $this->belongsTo(Location::class, 'pickup_location_id');
    }

    public function assignedRider()
    {
        return $this->belongsTo(\App\Models\Rider\Rider::class, 'assigned_driver_id');
    }

    public function deliveryEvents()
    {
        return $this->hasMany(\App\Models\Logistics\DeliveryEvent::class)->latest();
    }

    public function codHandovers()
    {
        return $this->hasMany(\App\Models\Logistics\CodHandoverAllocation::class);
    }

    public function codAdjustments()
    {
        return $this->hasMany(\App\Models\Logistics\CodAdjustment::class);
    }

    public function approvedCodAdjustmentNet(): float
    {
        $adjustments = $this->relationLoaded('codAdjustments')
            ? $this->codAdjustments->where('status', 'approved')
            : $this->codAdjustments()->where('status', 'approved')->get();

        return (float) $adjustments->sum(fn ($adjustment) => $adjustment->direction === 'decrease'
            ? (float) $adjustment->amount
            : -(float) $adjustment->amount);
    }

    public function codRemittedAmount(): float
    {
        return (float) ($this->cod_remitted_amount ?? 0);
    }

    public function logisticsStatusLabel(): string
    {
        return self::LOGISTICS_STATUS_LABELS[$this->logistics_status ?? 'pending']
            ?? ucwords(str_replace('_', ' ', (string) ($this->logistics_status ?? 'pending')));
    }

    public function canTransitionLogisticsTo(string $status): bool
    {
        return in_array($status, self::LOGISTICS_TRANSITIONS[$this->logistics_status ?? 'pending'] ?? [], true);
    }

    public function isCod(): bool
    {
        return in_array(strtolower((string) $this->payment_method), [
            'cod', 'payondelivery', 'pod', 'pay_on_delivery',
        ], true);
    }

    public function codExpected(): float
    {
        if (! $this->isCod() || $this->payment_status === 'paid') {
            return 0.0;
        }

        return (float) ($this->total_amount ?? 0);
    }

    public function codOutstanding(): float
    {
        if (! $this->isCod() || ! $this->cod_collected_at) {
            return 0.0;
        }

        return max(0.0, (float) ($this->cod_amount ?? $this->total_amount ?? 0) - $this->codRemittedAmount() - $this->approvedCodAdjustmentNet());
    }

    public function isDispatchable(): bool
    {
        return in_array($this->logistics_status ?? 'pending', ['pending', 'created', 'failed'], true);
    }

    public function statusLabel(): string
    {
        if ($this->isPickup()) {
            $pickup = [
                'dispatched' => 'In transit to pickup station',
                'completed' => 'Collected',
            ];

            if (isset($pickup[$this->status])) {
                return $pickup[$this->status];
            }
        }

        return self::STATUS_LABELS[$this->status] ?? ucwords(str_replace('_', ' ', (string) $this->status));
    }
}
