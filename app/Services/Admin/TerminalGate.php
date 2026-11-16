<?php

namespace App\Services\Admin;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Hash;

/**
 * Second factor for the admin terminal. An administrator sets a PIN once; from
 * then on every terminal session must be unlocked with that PIN before any
 * command, file read or file write is accepted.
 *
 * The PIN is stored only as a password hash and is never cached, so a change
 * takes effect immediately and the secret never lives in the page cache. The
 * unlock is scoped to the current staff session and expires automatically.
 */
class TerminalGate
{
    public const KEY = 'terminal_pin_hash';

    public const SESSION_KEY = 'admin.terminal.unlocked_at';

    /** Minutes a single unlock remains valid. */
    public const TTL_MINUTES = 30;

    /** Whether an administrator has configured a terminal PIN. */
    public function configured(): bool
    {
        return $this->hash() !== null;
    }

    public function setPin(string $pin): void
    {
        StoreSetting::updateOrCreate(['key' => self::KEY], ['value' => Hash::make($pin)]);
    }

    public function clearPin(): void
    {
        StoreSetting::where('key', self::KEY)->delete();
    }

    public function verify(string $pin): bool
    {
        $hash = $this->hash();

        return $hash !== null && Hash::check($pin, $hash);
    }

    public function unlock(): void
    {
        session([self::SESSION_KEY => now()->timestamp]);
    }

    public function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** Whether the current session has a valid, unexpired unlock. */
    public function unlocked(): bool
    {
        $at = session(self::SESSION_KEY);

        return is_numeric($at) && (now()->timestamp - (int) $at) < (self::TTL_MINUTES * 60);
    }

    public function remainingSeconds(): int
    {
        $at = (int) session(self::SESSION_KEY, 0);

        return max(0, (self::TTL_MINUTES * 60) - (now()->timestamp - $at));
    }

    protected function hash(): ?string
    {
        $value = StoreSetting::where('key', self::KEY)->value('value');

        return $value ?: null;
    }
}
