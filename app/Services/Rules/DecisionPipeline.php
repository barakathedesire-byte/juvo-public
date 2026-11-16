<?php

namespace App\Services\Rules;

/**
 * Enforces the platform's decision priority order:
 *
 *   auth → legal/policy → financial integrity → eligibility
 *        → configured rules → algorithm → UX
 *
 * Stages run strictly in that order and the first denial decides the outcome.
 * Because the hard stages (auth, legal/policy, financial integrity, eligibility)
 * run before the soft ones (configured rules, algorithm, UX), no algorithm or
 * configured rule can ever resurrect a decision that a hard control has denied.
 */
class DecisionPipeline
{
    public const ORDER = [
        'auth',
        'legal_policy',
        'financial_integrity',
        'eligibility',
        'configured_rules',
        'algorithm',
        'ux',
    ];

    public const HARD_STAGES = [
        'auth',
        'legal_policy',
        'financial_integrity',
        'eligibility',
    ];

    /**
     * @param  array<string, callable(): Decision>  $checks  keyed by stage name
     */
    public function evaluate(array $checks): Decision
    {
        $trace = [];

        foreach (self::ORDER as $stage) {
            if (! isset($checks[$stage])) {
                continue;
            }

            $result = $checks[$stage]();

            $trace[] = [
                'stage' => $stage,
                'allowed' => $result->allowed,
                'reason' => $result->reason,
                'hard' => $this->isHard($stage),
            ];

            if (! $result->allowed) {
                return new Decision(false, $stage, $result->reason, $trace);
            }
        }

        return new Decision(true, null, '', $trace);
    }

    public function isHard(string $stage): bool
    {
        return in_array($stage, self::HARD_STAGES, true);
    }

    public function priority(string $stage): int
    {
        $index = array_search($stage, self::ORDER, true);

        return $index === false ? PHP_INT_MAX : $index;
    }
}
