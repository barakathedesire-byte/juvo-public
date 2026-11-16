<?php

namespace App\Models\Logistics;

use Illuminate\Database\Eloquent\Model;

class RiskEvidence extends Model
{
    protected $table = 'logistics_risk_evidence';

    protected $fillable = ['case_id', 'evidence_type', 'reference', 'description', 'file_path', 'created_by'];
    public function case() { return $this->belongsTo(RiskCase::class, 'case_id'); }
    public function createdBy() { return $this->belongsTo(LogisticsAdmin::class, 'created_by'); }
}
