<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorVerificationDocument extends Model
{
    protected $fillable = [
        'vendor_id', 'type', 'title', 'file_path', 'disk', 'status',
        'checks', 'score', 'rejection_reason', 'reviewed_by', 'reviewed_at', 'expires_at',
    ];

    protected $casts = [
        'checks' => 'array',
        'reviewed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
