<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'notifiable_type',
        'notifiable_id',
        'data',
        'read_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function notifiable()
    {
        return $this->morphTo();
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public function scopeForUser($query, $userId, $userType = 'customer')
    {
        $model = match ($userType) {
            'customer' => Customer::class,
            'vendor' => Vendor::class,
            'logistics' => LogisticsAdmin::class,
            default => User::class,
        };

        return $query->where('notifiable_type', $model)->where('notifiable_id', $userId);
    }
}
