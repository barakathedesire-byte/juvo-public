<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    protected $table = 'logistics_predictions';

    protected $fillable = [
        'prediction_type', 'subject_type', 'subject_id', 'features', 'result',
        'score', 'risk_level', 'confidence', 'predicted_at', 'actual_at', 'actual_result',
    ];

    protected $casts = [
        'features' => 'array',
        'result' => 'array',
        'actual_result' => 'array',
        'score' => 'decimal:2',
        'confidence' => 'decimal:2',
        'predicted_at' => 'datetime',
        'actual_at' => 'datetime',
    ];
}
