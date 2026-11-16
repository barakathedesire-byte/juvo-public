<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesLead extends Model
{
    protected $fillable = [
        'sales_rep_id', 'name', 'email', 'phone', 'source', 'status', 'lost_reason',
        'notes', 'converted_order_id', 'converted_customer_id', 'converted_at',
        'region_id', 'location_id', 'interest', 'category_id', 'product_id',
        'needs', 'budget', 'timeline', 'last_contacted_at', 'next_followup_at',
        'referral_customer_id', 'referral_sales_rep_id',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'last_contacted_at' => 'datetime',
        'next_followup_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public const STATUSES = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];

    public const LOST_REASONS = [
        'not_interested' => 'Not interested',
        'cannot_afford' => 'Cannot afford',
        'unreachable' => 'Unreachable',
        'purchased_elsewhere' => 'Purchased elsewhere',
        'invalid_contact' => 'Invalid contact',
        'other' => 'Other',
    ];

    public const SOURCES = [
        'personal_referral' => 'Personal referral',
        'customer_referral' => 'Existing customer referral',
        'social_media' => 'Social media',
        'whatsapp' => 'WhatsApp',
        'community_outreach' => 'Community outreach',
        'walk_in' => 'Walk-in',
        'personal_network' => 'Personal network',
        'other' => 'Other',
    ];

    public function rep()
    {
        return $this->belongsTo(SalesRep::class, 'sales_rep_id');
    }

    public function region()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function convertedOrder()
    {
        return $this->belongsTo(Order::class, 'converted_order_id');
    }

    public function convertedCustomer()
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function referralCustomer()
    {
        return $this->belongsTo(Customer::class, 'referral_customer_id');
    }

    public function referralSalesRep()
    {
        return $this->belongsTo(SalesRep::class, 'referral_sales_rep_id');
    }

    public function notes()
    {
        return $this->hasMany(LeadNote::class, 'sales_lead_id')->latest();
    }

    public function followUps()
    {
        return $this->hasMany(LeadFollowUp::class, 'sales_lead_id')->latest('scheduled_at');
    }

    public function statusHistories()
    {
        return $this->hasMany(LeadStatusHistory::class, 'sales_lead_id')->latest();
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', ['new', 'contacted', 'interested', 'follow_up']);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['new', 'contacted', 'interested', 'follow_up'], true);
    }
}
