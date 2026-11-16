<?php

namespace App\Support;

/**
 * Decimal-safe money helper.
 *
 * Money is stored in the database as `decimal(14,2)`; in PHP we never rely on
 * binary floating point for authoritative arithmetic. Internally amounts are
 * held as integer minor units (cents) so additions, splits and percentage
 * discounts are exact and remainders are deterministic.
 */
final class Money
{
    private function __construct(public readonly int $minor) {}

    public static function of(int|float|string|null $amount): self
    {
        $value = (float) ($amount ?? 0);

        return new self((int) round($value * 100));
    }

    public static function fromMinor(int $minor): self
    {
        return new self($minor);
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function sub(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    public function mul(int $factor): self
    {
        return new self($this->minor * $factor);
    }

    public function percent(float $percent): self
    {
        return new self((int) round($this->minor * ($percent / 100)));
    }

    public function min(self $other): self
    {
        return $this->minor <= $other->minor ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->minor >= $other->minor ? $this : $other;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function toFloat(): float
    {
        return round($this->minor / 100, 2);
    }

    /**
     * Split this amount into proportional integer parts that sum exactly back
     * to the original, giving the rounding remainder to the last slice.
     *
     * @param  array<int|string, int|float>  $weights  keyed shares (need not be normalised)
     * @return array<int|string, float> keyed amounts
     */
    public function allocate(array $weights): array
    {
        $keys = array_keys($weights);
        $total = array_sum($weights);
        $result = [];
        $running = 0;

        foreach ($keys as $i => $key) {
            $isLast = $i === count($keys) - 1;
            if ($isLast) {
                $part = $this->minor - $running;
            } elseif ($total > 0) {
                $part = (int) floor($this->minor * ($weights[$key] / $total));
            } else {
                $part = (int) floor($this->minor / count($keys));
            }
            $result[$key] = round($part / 100, 2);
            $running += $part;
        }

        return $result;
    }
}
