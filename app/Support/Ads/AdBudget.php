<?php

namespace App\Support\Ads;

use App\Models\BusinessRule;

/**
 * Resolves the effective minimum ad budget from the platform business rules.
 *
 * The canonical key is `ad_min_budget` (the one shown in the admin Business
 * Rules screen). `min_ad_budget` is a deprecated duplicate retained for older
 * data; it is only consulted when the canonical value is unset or zero so the
 * vendor portal and the approval engine always agree with what admins configure.
 */
class AdBudget
{
    public const CANONICAL_KEY = 'ad_min_budget';

    public const LEGACY_KEY = 'min_ad_budget';

    public static function minimum(): float
    {
        $canonical = (float) BusinessRule::number(self::CANONICAL_KEY, 0);

        if ($canonical > 0) {
            return $canonical;
        }

        return (float) BusinessRule::number(self::LEGACY_KEY, 0);
    }
}
