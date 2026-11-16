<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AlgorithmVersion extends Model
{
    protected $fillable = [
        'algorithm_id', 'version', 'config', 'status', 'notes',
        'created_by', 'activated_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'config' => 'array',
        'activated_at' => 'datetime',
    ];

    public function algorithm()
    {
        return $this->belongsTo(Algorithm::class);
    }
}
