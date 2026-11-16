<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IntegrationEvent extends Model
{
    protected $table = 'integration_events';

    protected $fillable = ['event_id', 'event_type', 'source', 'target', 'payload', 'status', 'attempts', 'error', 'processed_at'];
    protected $casts = ['payload' => 'array', 'processed_at' => 'datetime', 'attempts' => 'integer'];
}
