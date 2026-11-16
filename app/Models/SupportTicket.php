<?php

namespace App\Models;

use App\Services\Support\SupportSlaService;
use Illuminate\Database\Eloquent\Model;

class SupportTicket extends Model
{
    protected $fillable = [
        'ticket_number', 'vendor_id', 'customer_id', 'guest_name', 'guest_email',
        'product_id', 'order_id', 'rider_id', 'subject', 'category', 'dispute_type',
        'priority', 'status', 'resolution_category', 'resolution_notes',
        'assigned_to', 'assigned_logistics_admin_id', 'last_reply_at',
        'first_response_due_at', 'resolution_due_at', 'first_responded_at',
        'resolved_at', 'escalated_at', 'escalation_level', 'reopened_count',
    ];

    protected $casts = [
        'last_reply_at' => 'datetime',
        'first_response_due_at' => 'datetime',
        'resolution_due_at' => 'datetime',
        'first_responded_at' => 'datetime',
        'resolved_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalation_level' => 'integer',
        'reopened_count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket): void {
            if ($ticket->first_response_due_at || $ticket->resolution_due_at) {
                return;
            }

            $deadlines = app(SupportSlaService::class)->deadlinesFor($ticket->priority ?: 'normal');
            $ticket->first_response_due_at = $deadlines['first_response_due_at'];
            $ticket->resolution_due_at = $deadlines['resolution_due_at'];
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function rider()
    {
        return $this->belongsTo(\App\Models\Rider\Rider::class);
    }

    public function logisticsAssignee()
    {
        return $this->belongsTo(\App\Models\Logistics\LogisticsAdmin::class, 'assigned_logistics_admin_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages()
    {
        return $this->hasMany(SupportTicketMessage::class);
    }
}
