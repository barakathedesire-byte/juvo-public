<?php

namespace App\Services\Ads;

use App\Models\AdAccount;
use App\Models\AdBudgetReservation;
use App\Models\AdCampaign;
use App\Models\AdLedgerEntry;
use App\Models\Vendor;
use App\Support\Ads\Money;
use Illuminate\Support\Facades\DB;

/**
 * Append-only advertising ledger. Every money movement is written as an
 * immutable entry; balances are cached on the account for fast reads and can be
 * rebuilt from the ledger at any time via reconcile(). Entries are idempotent
 * by reference so retries never double-charge.
 */
class AdLedgerService
{
    public function accountFor(Vendor|int $vendor, ?string $currency = null): AdAccount
    {
        $id = $vendor instanceof Vendor ? $vendor->getKey() : $vendor;

        return AdAccount::firstOrCreate(
            ['vendor_id' => $id],
            ['currency' => $currency ?: Money::DEFAULT_CURRENCY, 'status' => 'active']
        );
    }

    protected function resolveAccount(Vendor|AdAccount|AdCampaign $subject): AdAccount
    {
        if ($subject instanceof AdAccount) {
            return $subject;
        }

        if ($subject instanceof AdCampaign) {
            return $this->accountFor($subject->vendor_id, $subject->spendCurrency());
        }

        return $this->accountFor($subject);
    }

    public function fund(Vendor|AdAccount|AdCampaign $subject, int $amountMinor, ?string $reference = null, array $meta = []): ?AdLedgerEntry
    {
        return $this->post($this->resolveAccount($subject), 'funding', 'credit', $amountMinor, $reference, null, $meta);
    }

    public function refund(AdCampaign|AdAccount $subject, int $amountMinor, ?string $reference = null, array $meta = []): ?AdLedgerEntry
    {
        return $this->post($subject, 'refund', 'credit', $amountMinor, $reference, $meta);
    }

    public function bonus(AdCampaign|AdAccount $subject, int $amountMinor, ?string $reference = null, array $meta = []): ?AdLedgerEntry
    {
        return $this->post($subject, 'bonus', 'credit', $amountMinor, $reference, $meta);
    }

    public function adjust(AdCampaign|AdAccount $subject, int $amountMinor, bool $credit = true, ?string $reference = null, array $meta = []): AdLedgerEntry
    {
        return $this->post($subject, 'adjustment', $credit ? 'credit' : 'debit', $amountMinor, $reference, $meta);
    }

    /**
     * Fund a campaign from a verified payment. Idempotent per campaign+amount.
     */
    public function fundCampaign(AdCampaign $campaign, int $amountMinor, ?string $reference = null, array $meta = []): ?AdLedgerEntry
    {
        if ($amountMinor <= 0) {
            return null;
        }

        $reference = $reference ?: 'ads:funding:campaign:'.$campaign->id.':'.$amountMinor;

        return DB::transaction(function () use ($campaign, $amountMinor, $reference, $meta) {
            if ($existing = AdLedgerEntry::where('reference', $reference)->first()) {
                return $existing;
            }

            $account = $this->lockAccount($campaign);

            if (! $account) {
                return null;
            }

            return $this->applyAndWrite(
                $account,
                'funding',
                'credit',
                $amountMinor,
                $reference,
                $campaign,
                $meta ?: ['source' => 'payment']
            );
        });
    }

    /**
     * Hold budget so a later spend can never exceed the account balance.
     */
    public function reserve(AdCampaign $campaign, int $amountMinor, string $period = 'lifetime', ?string $periodKey = null, ?string $reference = null, array $meta = []): ?AdBudgetReservation
    {
        if ($amountMinor <= 0) {
            return null;
        }

        $periodKey = $periodKey ?: ($period === 'day' ? today()->toDateString() : 'lifetime');
        $reference = $reference ?: $this->reference('reserve', $campaign, $periodKey, $amountMinor);

        return DB::transaction(function () use ($campaign, $amountMinor, $period, $periodKey, $reference, $meta) {
            $account = $this->lockAccount($campaign);

            if (! $account || ! $account->isActive()) {
                return null;
            }

            if (AdBudgetReservation::where('reference', $reference)->exists()) {
                return AdBudgetReservation::where('reference', $reference)->first();
            }

            if ($account->available_minor < $amountMinor) {
                return null;
            }

            $this->applyAndWrite($account, 'reservation', 'credit', $amountMinor, $reference, $campaign, $meta);

            $campaign->reserved_minor = ($campaign->reserved_minor ?? 0) + $amountMinor;
            $campaign->timestamps = false;
            $campaign->save();
            $campaign->timestamps = true;

            return AdBudgetReservation::create([
                'campaign_id' => $campaign->id,
                'account_id' => $account->id,
                'period' => $period,
                'period_key' => $periodKey,
                'amount_minor' => $amountMinor,
                'currency' => $account->currency,
                'status' => 'held',
                'reference' => $reference,
            ]);
        });
    }

    /**
     * Convert a spend. If a reservation is supplied it is released first so the
     * held amount becomes realised spend. Returns null when funds are
     * insufficient (never overspends).
     */
    public function charge(AdCampaign $campaign, int $amountMinor, ?string $reference = null, array $meta = [], ?AdBudgetReservation $reservation = null): ?AdLedgerEntry
    {
        if ($amountMinor <= 0) {
            return null;
        }

        $reference = $reference ?: $this->reference('spend', $campaign, null, $amountMinor);

        return DB::transaction(function () use ($campaign, $amountMinor, $reference, $meta, $reservation) {
            $existing = AdLedgerEntry::where('reference', $reference)->first();
            if ($existing) {
                return $existing;
            }

            $account = $this->lockAccount($campaign);

            if (! $account || ! $account->isActive()) {
                return null;
            }

            if ($reservation && $reservation->status !== 'held') {
                $reservation = null;
            }

            $available = $account->available_minor + ($reservation ? $reservation->amount_minor : 0);

            if ($available < $amountMinor) {
                return null;
            }

            if ($reservation) {
                $this->applyAndWrite($account, 'release', 'debit', $reservation->amount_minor, $reference.':release', $campaign, $meta);
                $reservation->status = 'committed';
                $reservation->save();

                $campaign->reserved_minor = max(0, ($campaign->reserved_minor ?? 0) - $reservation->amount_minor);
            }

            $entry = $this->applyAndWrite($account, 'spend', 'debit', $amountMinor, $reference, $campaign, $meta);

            $campaign->spent_minor = ($campaign->spent_minor ?? 0) + $amountMinor;
            $campaign->spent = Money::decimal($campaign->spent_minor);
            $campaign->timestamps = false;
            $campaign->save();
            $campaign->timestamps = true;

            return $entry;
        });
    }

    public function releaseReservation(AdBudgetReservation $reservation, ?string $reference = null, array $meta = []): ?AdLedgerEntry
    {
        if ($reservation->status !== 'held') {
            return null;
        }

        $reference = $reference ?: $reservation->reference.':expire';

        return DB::transaction(function () use ($reservation, $reference, $meta) {
            $account = AdAccount::whereKey($reservation->account_id)->lockForUpdate()->first();
            if (! $account) {
                return null;
            }

            $entry = $this->applyAndWrite($account, 'release', 'debit', $reservation->amount_minor, $reference, $reservation->campaign, $meta);

            $reservation->status = 'released';
            $reservation->save();

            if ($reservation->campaign) {
                $reservation->campaign->reserved_minor = max(0, ($reservation->campaign->reserved_minor ?? 0) - $reservation->amount_minor);
                $reservation->campaign->timestamps = false;
                $reservation->campaign->save();
                $reservation->campaign->timestamps = true;
            }

            return $entry;
        });
    }

    /** Recompute an account's cached balances from the ledger. */
    public function reconcile(AdAccount $account): array
    {
        $entries = AdLedgerEntry::where('account_id', $account->id)->get();

        $funded = 0;
        $spent = 0;
        $reserved = 0;

        foreach ($entries as $entry) {
            $amount = (int) $entry->amount_minor;
            match ($entry->type) {
                'funding', 'bonus', 'refund' => $funded += $amount,
                'reservation' => $reserved += $amount,
                'release' => $reserved -= $amount,
                'spend' => $spent += $amount,
                'adjustment' => $entry->direction === 'credit' ? $funded += $amount : $spent += $amount,
                default => null,
            };
        }

        $reserved = max(0, $reserved);

        $diff = [
            'funded' => $funded - $account->funded_minor,
            'spent' => $spent - $account->spent_minor,
            'reserved' => $reserved - $account->reserved_minor,
        ];

        $account->funded_minor = $funded;
        $account->spent_minor = $spent;
        $account->reserved_minor = $reserved;
        $account->save();

        return $diff;
    }

    public function availableFor(AdCampaign $campaign): int
    {
        $remaining = $campaign->budgetRemainingMinor();
        $account = AdAccount::where('vendor_id', $campaign->vendor_id)->first();

        if (! $account) {
            return $remaining;
        }

        return max(0, min($remaining, $account->available_minor));
    }

    public function dailySpentMinor(AdCampaign $campaign, ?string $date = null): int
    {
        $date = $date ?: today()->toDateString();

        return (int) AdLedgerEntry::where('campaign_id', $campaign->id)
            ->where('type', 'spend')
            ->whereDate('created_at', $date)
            ->sum('amount_minor');
    }

    public function dailyHeldMinor(AdCampaign $campaign, ?string $date = null): int
    {
        $date = $date ?: today()->toDateString();

        return (int) AdBudgetReservation::where('campaign_id', $campaign->id)
            ->where('period', 'day')
            ->where('period_key', $date)
            ->where('status', 'held')
            ->sum('amount_minor');
    }

    protected function post(AdCampaign|AdAccount $subject, string $type, string $direction, int $amountMinor, ?string $reference, ?array $meta = [], ?array $actor = null): AdLedgerEntry
    {
        $meta = $meta ?? [];
        $campaign = $subject instanceof AdCampaign ? $subject : null;
        $reference = $reference ?: $this->reference($type, $campaign, null, $amountMinor);

        return DB::transaction(function () use ($subject, $campaign, $type, $direction, $amountMinor, $reference, $meta, $actor) {
            $existing = AdLedgerEntry::where('reference', $reference)->first();
            if ($existing) {
                return $existing;
            }

            $account = $subject instanceof AdAccount
                ? AdAccount::whereKey($subject->id)->lockForUpdate()->first()
                : $this->lockAccount($campaign);

            if (! $account) {
                throw new \RuntimeException('Advertising account not found for ledger entry.');
            }

            return $this->applyAndWrite($account, $type, $direction, $amountMinor, $reference, $campaign, $meta, $actor);
        });
    }

    protected function applyAndWrite(AdAccount $account, string $type, string $direction, int $amountMinor, string $reference, ?AdCampaign $campaign = null, array $meta = [], ?array $actor = null): AdLedgerEntry
    {
        match ($type) {
            'funding', 'bonus', 'refund' => $account->funded_minor += $amountMinor,
            'adjustment' => $direction === 'credit'
                ? $account->funded_minor += $amountMinor
                : $account->spent_minor += $amountMinor,
            'reservation' => $account->reserved_minor += $amountMinor,
            'release' => $account->reserved_minor = max(0, $account->reserved_minor - $amountMinor),
            'spend' => $account->spent_minor += $amountMinor,
            default => null,
        };

        $account->timestamps = false;
        $account->save();
        $account->timestamps = true;

        return AdLedgerEntry::create([
            'account_id' => $account->id,
            'vendor_id' => $account->vendor_id,
            'campaign_id' => $campaign?->id,
            'type' => $type,
            'direction' => $direction,
            'amount_minor' => $amountMinor,
            'currency' => $account->currency,
            'balance_after_minor' => $account->available_minor,
            'reserved_after_minor' => $account->reserved_minor,
            'reference' => $reference,
            'actor_type' => $actor['type'] ?? null,
            'actor_id' => $actor['id'] ?? null,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    protected function lockAccount(?AdCampaign $campaign): ?AdAccount
    {
        if (! $campaign) {
            return null;
        }

        $account = AdAccount::firstOrCreate(
            ['vendor_id' => $campaign->vendor_id],
            ['currency' => $campaign->spendCurrency(), 'status' => 'active']
        );

        return AdAccount::whereKey($account->id)->lockForUpdate()->first();
    }

    protected function reference(string $type, ?AdCampaign $campaign, ?string $periodKey, int $amountMinor): string
    {
        return implode(':', array_filter([
            'ads', $type, $campaign?->id, $periodKey, $amountMinor, uniqid('', true),
        ]));
    }
}
