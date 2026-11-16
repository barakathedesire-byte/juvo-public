<?php

namespace App\Models\Rider;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Rider extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $guard = 'rider';

    protected $table = 'riders';

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'national_id',
        'date_of_birth',
        'gender',
        'address',
        'city',
        'state',
        'postal_code',
        'vehicle_type', // motorcycle, bicycle, van, truck
        'vehicle_make',
        'vehicle_model',
        'vehicle_year',
        'vehicle_color',
        'license_plate',
        'license_number',
        'license_expiry',
        'kyc_status', // pending, under_review, approved, rejected
        'kyc_documents',
        'kyc_checks',
        'kyc_score',
        'kyc_submitted_at',
        'kyc_reviewed_at',
        'kyc_reviewed_by',
        'kyc_rejection_reason',
        'bank_name',
        'bank_account_name',
        'bank_account_number',
        'bank_branch',
        'payout_method',
        'mobile_money_provider',
        'mobile_money_number',
        'mobile_money_account_name',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'rating',
        'total_deliveries',
        'is_active',
        'is_online',
        'last_online_at',
        'current_lat',
        'current_lng',
        'profile_image',
        'background_check_status',
        'background_check_completed_at',
    ];

    protected $hidden = [
        'password',
        'national_id',
        'bank_account_number',
        'kyc_documents',
        'kyc_checks',
        'kyc_score',
    ];

    protected $casts = [
        'password' => 'hashed',
        'date_of_birth' => 'date',
        'license_expiry' => 'date',
        'kyc_documents' => 'array',
        'kyc_checks' => 'array',
        'kyc_submitted_at' => 'datetime',
        'kyc_reviewed_at' => 'datetime',
        'is_active' => 'boolean',
        'is_online' => 'boolean',
        'last_online_at' => 'datetime',
        'current_lat' => 'decimal:8',
        'current_lng' => 'decimal:8',
        'rating' => 'decimal:2',
        'background_check_completed_at' => 'datetime',
    ];

    public const KYC_STATUSES = [
        'pending' => 'Pending Submission',
        'under_review' => 'Under Review',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ];

    public const VEHICLE_TYPES = [
        'motorcycle' => 'Motorcycle',
        'bicycle' => 'Bicycle',
        'van' => 'Van',
        'truck' => 'Truck',
    ];

    public const KYC_DOCUMENT_TYPES = [
        'national_id_front' => 'National ID (Front)',
        'national_id_back' => 'National ID (Back)',
        'driving_license' => 'Driving License',
        'vehicle_registration' => 'Vehicle Registration',
        'vehicle_insurance' => 'Vehicle Insurance',
        'passport_photo' => 'Passport Photo',
        'proof_of_address' => 'Proof of Address',
        'bank_statement' => 'Bank Statement',
        'background_check' => 'Background Check Certificate',
    ];

    public function kycReviewedBy()
    {
        return $this->belongsTo(\App\Models\Logistics\LogisticsAdmin::class, 'kyc_reviewed_by');
    }

    public function assignedOrders()
    {
        return $this->hasMany(\App\Models\Order::class, 'assigned_driver_id');
    }

    public function deliveryEvents()
    {
        return $this->hasMany(\App\Models\Logistics\DeliveryEvent::class, 'rider_id');
    }

    public function codHandovers()
    {
        return $this->hasMany(\App\Models\Logistics\CodHandover::class, 'rider_id');
    }

    public function codAdjustments()
    {
        return $this->hasMany(\App\Models\Logistics\CodAdjustment::class, 'rider_id');
    }

    public function ledgerEntries()
    {
        return $this->hasMany(RiderLedgerEntry::class, 'rider_id');
    }

    public function payouts()
    {
        return $this->hasMany(RiderPayout::class, 'rider_id');
    }

    public function isEligibleForWork(): bool
    {
        return $this->is_active
            && $this->isKycApproved()
            && $this->background_check_status !== 'flagged'
            && (! $this->license_expiry || $this->license_expiry->isFuture());
    }

    public function eligibilityReason(): ?string
    {
        if (! $this->is_active) {
            return 'This rider account is suspended.';
        }

        if (! $this->isKycApproved()) {
            return 'KYC approval is still pending.';
        }

        if ($this->background_check_status === 'flagged') {
            return 'Background check is flagged.';
        }

        if ($this->license_expiry && ! $this->license_expiry->isFuture()) {
            return 'The riding license has expired.';
        }

        return null;
    }

    public function getFullNameAttribute(): string
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function isKycApproved(): bool
    {
        return $this->kyc_status === 'approved';
    }

    public function isKycPending(): bool
    {
        return in_array($this->kyc_status, ['pending', 'under_review'], true);
    }

    public function getMissingKycDocuments(): array
    {
        $required = [
            'national_id_front',
            'national_id_back',
            'driving_license',
            'vehicle_registration',
            'vehicle_insurance',
            'passport_photo',
            'proof_of_address',
            'bank_statement',
        ];

        $submitted = array_keys($this->kyc_documents ?? []);

        return array_diff($required, $submitted);
    }
}
