<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AutomationRunLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['run_id', 'level', 'message', 'context', 'created_at'];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function run()
    {
        return $this->belongsTo(AutomationRun::class, 'run_id');
    }
}
