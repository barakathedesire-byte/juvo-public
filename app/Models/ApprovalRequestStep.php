<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequestStep extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'approval_request_id', 'actor_id', 'action', 'reason', 'meta', 'created_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'created_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(ApprovalRequest::class, 'approval_request_id');
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
