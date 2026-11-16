<?php

namespace App\Services\Ads;

use App\Models\AdAuditLog;
use App\Models\AdCampaign;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AdAuditService
{
    public function log(string $action, array $context = []): AdAuditLog
    {
        $actor = $this->actor();

        return AdAuditLog::create([
            'actor_type' => $actor['type'] ?? null,
            'actor_id' => $actor['id'] ?? null,
            'action' => $action,
            'campaign_id' => $context['campaign_id'] ?? null,
            'account_id' => $context['account_id'] ?? null,
            'subject_type' => $context['subject_type'] ?? null,
            'subject_id' => $context['subject_id'] ?? null,
            'before' => $context['before'] ?? null,
            'after' => $context['after'] ?? null,
            'reason' => $context['reason'] ?? null,
            'ip' => $context['ip'] ?? (app()->runningInConsole() ? null : request()->ip()),
            'created_at' => now(),
        ]);
    }

    public function logCampaign(string $action, AdCampaign $campaign, ?array $before = null, ?array $after = null, ?string $reason = null): AdAuditLog
    {
        return $this->log($action, [
            'campaign_id' => $campaign->id,
            'subject_type' => AdCampaign::class,
            'subject_id' => $campaign->id,
            'before' => $before,
            'after' => $after,
            'reason' => $reason,
        ]);
    }

    public function logModel(string $action, Model $model, ?array $before = null, ?array $after = null): AdAuditLog
    {
        return $this->log($action, [
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'before' => $before,
            'after' => $after,
        ]);
    }

    protected function actor(): array
    {
        foreach (['web', 'vendor', 'customer'] as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();

                return ['type' => $guard, 'id' => $user->getAuthIdentifier()];
            }
        }

        return ['type' => null, 'id' => null];
    }
}
