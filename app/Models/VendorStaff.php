<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class VendorStaff extends Authenticatable
{
    use Notifiable;

    protected $table = 'vendor_staff';

    protected $guard = 'vendor_staff';

    protected $fillable = [
        'vendor_id', 'name', 'email', 'password', 'role', 'status', 'last_login_at',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'password' => 'hashed',
        'last_login_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }
}
