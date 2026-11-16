<?php

namespace App\Services\Rules;

/**
 * A single allow/deny outcome produced by one stage of a decision, or the
 * final consolidated outcome from a DecisionPipeline.
 */
final class Decision
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $stage = null,
        public readonly string $reason = '',
        public readonly array $trace = [],
    ) {}

    public static function allow(string $stage = 'ux', string $reason = ''): self
    {
        return new self(true, $stage, $reason);
    }

    public static function deny(string $stage, string $reason): self
    {
        return new self(false, $stage, $reason);
    }

    public function isAllowed(): bool
    {
        return $this->allowed;
    }

    public function blockedBy(): ?string
    {
        return $this->allowed ? null : $this->stage;
    }
}
