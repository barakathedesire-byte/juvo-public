<?php

namespace App\Console\Commands;

use App\Models\AdAccount;
use App\Services\Ads\AdAttributionService;
use App\Services\Ads\AdLedgerService;
use Illuminate\Console\Command;

class ReconcileAds extends Command
{
    protected $signature = 'ads:reconcile';

    protected $description = 'Reconcile advertising ledger balances and expire stale attribution touchpoints.';

    public function handle(AdLedgerService $ledger, AdAttributionService $attribution): int
    {
        $drift = 0;

        foreach (AdAccount::cursor() as $account) {
            $diff = $ledger->reconcile($account);
            if (array_filter($diff)) {
                $drift++;
                $this->warn(sprintf(
                    'Account #%d drift: funded %+d, spent %+d, reserved %+d (minor units)',
                    $account->id, $diff['funded'], $diff['spent'], $diff['reserved']
                ));
            }
        }

        $expired = $attribution->expireStale();

        $this->info("Ads reconcile complete: ".AdAccount::count()." accounts checked, {$drift} with drift, {$expired} touchpoints expired.");

        return self::SUCCESS;
    }
}
