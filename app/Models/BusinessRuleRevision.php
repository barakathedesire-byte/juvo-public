<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessRuleRevision extends Model
{
    protected $fillable = [
        'business_rule_id', 'key', 'version', 'value', 'is_active',
        'effective_from', 'effective_to', 'changed_by', 'reason', 'snapshot',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'version' => 'integer',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'snapshot' => 'array',
    ];

    public function rule()
    {
        return $this->belongsTo(BusinessRule::class, 'business_rule_id');
    }
}
