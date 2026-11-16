<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorAppeal extends Model
{
    protected $fillable = [
        'vendor_id', 'subject', 'message', 'status', 'related_type', 'related_id',
        'resolution', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
