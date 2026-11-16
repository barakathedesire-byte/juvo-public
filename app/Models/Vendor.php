<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Vendor extends Authenticatable
{
    use Notifiable;

    protected $guard = 'vendor';

    protected $fillable = [
        'name', 'shop_name', 'email', 'password', 'phone', 'address', 'status', 'is_approved', 'profile_image',
        'tin_number', 'registration_number', 'business_documents',
        'email_verified_at', 'verification_code', 'ad_restricted', 'ad_credit_limit',
        'banner', 'opening_hours', 'delivery_coverage',
        'latitude', 'longitude', 'verification_status', 'verified_at',
        'verified_by', 'suspended_at', 'suspension_reason', 'commission_rate', 'minimum_payout',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'is_approved' => 'boolean',
        'business_documents' => 'array',
        'email_verified_at' => 'datetime',
        'ad_restricted' => 'boolean',
        'ad_credit_limit' => 'float',
        'opening_hours' => 'array',
        'delivery_coverage' => 'array',
        'verified_at' => 'datetime',
        'suspended_at' => 'datetime',
        'commission_rate' => 'float',
        'minimum_payout' => 'float',
    ];

    /**
     * Commission rate applied to this vendor's sales. Falls back to the
     * platform default (business rule / store setting), then 0.
     */
    public function effectiveCommissionRate(): float
    {
        if ($this->commission_rate !== null) {
            return (float) $this->commission_rate;
        }

        return (float) \App\Models\BusinessRule::number('default_commission_rate', 0);
    }

    public function adCampaigns()
    {
        return $this->hasMany(AdCampaign::class, 'vendor_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'vendor_id');
    }

    public function shop()
    {
        return $this->hasOne(Shop::class, 'vendor_id');
    }

    public function verificationDocuments()
    {
        return $this->hasMany(VendorVerificationDocument::class, 'vendor_id');
    }

    public function commissions()
    {
        return $this->hasMany(VendorCommission::class, 'vendor_id');
    }

    public function transactions()
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id');
    }

    public function payouts()
    {
        return $this->hasMany(VendorPayout::class, 'vendor_id');
    }

    public function payoutMethods()
    {
        return $this->hasMany(VendorPayoutMethod::class, 'vendor_id');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }

    public function supportTickets()
    {
        return $this->hasMany(SupportTicket::class, 'vendor_id');
    }

    public function appeals()
    {
        return $this->hasMany(VendorAppeal::class, 'vendor_id');
    }

    public function staff()
    {
        return $this->hasMany(VendorStaff::class, 'vendor_id');
    }

    public function documentPaths(): array
    {
        return is_array($this->business_documents) ? $this->business_documents : [];
    }

    public function isApproved(): bool
    {
        return (bool) $this->is_approved && $this->status === 'active';
    }

    public function isSuspended(): bool
    {
        return $this->status !== 'active';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\VendorResetPasswordNotification($token));
    }
}
