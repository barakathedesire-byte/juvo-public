<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_type', 'actor_id', 'action', 'auditable_type', 'auditable_id',
        'before', 'after', 'reason', 'ip', 'user_agent',
    ];

    protected $casts = [
        'before' => 'array',
        'after' => 'array',
    ];

    public function auditable()
    {
        return $this->morphTo();
    }

    public function actor()
    {
        return $this->morphTo();
    }
}
