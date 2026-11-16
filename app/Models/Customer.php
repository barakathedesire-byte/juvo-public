<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'national_id',
        'address',
        'next_of_kin_name',
        'next_of_kin_phone',
        'status',
        'profile_image',
        'google_id',
        'sales_rep_id',
        'region_id',
        'location_id',
        'delivery_address',
        'crm_status',
        'last_contacted_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'last_contacted_at' => 'datetime',
    ];

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
    }

    public function wishlistProducts()
    {
        return $this->belongsToMany(Product::class, 'wishlists', 'customer_id', 'product_id')
            ->withTimestamps();
    }

    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }

    public function addresses()
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default')->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function salesRep()
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

    public function notes()
    {
        return $this->hasMany(CustomerNote::class)->latest();
    }

    public function followUps()
    {
        return $this->hasMany(CustomerFollowUp::class)->latest('scheduled_at');
    }

    public function notifications()
    {
        return $this->morphMany(Notification::class, 'notifiable');
    }
}
