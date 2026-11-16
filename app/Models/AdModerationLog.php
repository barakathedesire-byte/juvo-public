<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdModerationLog extends Model
{
    protected $fillable = [
        'campaign_id', 'admin_id', 'action', 'from_status', 'to_status', 'note',
    ];

    public function campaign()
    {
        return $this->belongsTo(AdCampaign::class, 'campaign_id');
    }

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
