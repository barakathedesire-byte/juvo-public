<?php

namespace App\Services\Intelligence;

use App\Automation\Actions\AdCampaignApprovalAction;
use App\Automation\Actions\LogisticsCreateSupportTicketAction;
use App\Automation\Actions\LogisticsSendNotificationAction;
use App\Automation\Actions\AgentCommissionReconcileAction;
use App\Automation\Actions\AgentFraudScanAction;
use App\Automation\Actions\AgentRankEvaluationAction;
use App\Automation\Actions\DetectAnomaliesAction;
use App\Automation\Actions\ExpirePromotionsAction;
use App\Automation\Actions\LowStockAlertAction;
use App\Automation\Actions\NoopAction;
use App\Automation\Actions\OrderReadyForPickupAction;
use App\Automation\Actions\RecomputeAssociationsAction;
use App\Automation\Actions\RefreshMarketplaceMetricsAction;
use App\Automation\Actions\ReindexSearchIndexAction;
use App\Automation\Actions\VendorOnboardingApprovalAction;
use App\Automation\AutomationAction;
use RuntimeException;

/**
 * The catalogue of actions an automation workflow is allowed to invoke. Only
 * actions registered here can ever run — a workflow referencing an unknown
 * action fails loudly rather than silently doing nothing.
 */
class AutomationRegistry
{
    /** @var array<int, class-string<AutomationAction>> */
    protected array $actions = [
        NoopAction::class,
        RecomputeAssociationsAction::class,
        RefreshMarketplaceMetricsAction::class,
        DetectAnomaliesAction::class,
        ReindexSearchIndexAction::class,
        ExpirePromotionsAction::class,
        VendorOnboardingApprovalAction::class,
        AdCampaignApprovalAction::class,
        OrderReadyForPickupAction::class,
        LowStockAlertAction::class,
        AgentRankEvaluationAction::class,
        AgentCommissionReconcileAction::class,
        AgentFraudScanAction::class,
        LogisticsSendNotificationAction::class,
        LogisticsCreateSupportTicketAction::class,
    ];

    /** @var array<string, AutomationAction>|null */
    protected ?array $resolved = null;

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function get(string $key): AutomationAction
    {
        $all = $this->all();

        if (! isset($all[$key])) {
            throw new RuntimeException("Unregistered automation action [{$key}].");
        }

        return $all[$key];
    }

    /**
     * @return array<string, AutomationAction>
     */
    public function all(): array
    {
        if ($this->resolved === null) {
            $this->resolved = [];
            foreach ($this->actions as $class) {
                /** @var AutomationAction $action */
                $action = app($class);
                $this->resolved[$action->key()] = $action;
            }
        }

        return $this->resolved;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function catalogue(): array
    {
        return collect($this->all())->map(fn (AutomationAction $a) => [
            'key' => $a->key(),
            'label' => $a->label(),
            'mutates' => $a->mutates(),
        ])->values()->all();
    }
}
