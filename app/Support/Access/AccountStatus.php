<?php

namespace App\Support\Access;

/**
 * Canonical staff account states for the unified admin portal.
 *
 * Only ACTIVE accounts with approved portal access may authenticate into the
 * admin portal. The remaining states are surfaced to administrators and shown
 * as a clear, non-sensitive message to the account owner.
 */
class AccountStatus
{
    public const INVITATION_PENDING = 'invitation_pending';

    public const AWAITING_APPROVAL = 'awaiting_approval';

    public const ACTIVE = 'active';

    public const LOCKED = 'locked';

    public const DISABLED = 'disabled';

    public const SUSPENDED = 'suspended';

    public const REVOKED = 'revoked';

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return [
            self::INVITATION_PENDING,
            self::AWAITING_APPROVAL,
            self::ACTIVE,
            self::LOCKED,
            self::DISABLED,
            self::SUSPENDED,
            self::REVOKED,
        ];
    }

    /**
     * States that may sign in to the staff portal.
     *
     * @return array<int, string>
     */
    public static function eligible(): array
    {
        return [self::ACTIVE];
    }

    public static function isEligible(?string $status): bool
    {
        // A null status (e.g. a freshly created row before the DB default is
        // read back) is treated as the default eligible state.
        return in_array($status ?? self::ACTIVE, self::eligible(), true);
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            self::INVITATION_PENDING => 'Invitation pending',
            self::AWAITING_APPROVAL => 'Awaiting approval',
            self::ACTIVE => 'Active',
            self::LOCKED => 'Temporarily locked',
            self::DISABLED => 'Disabled',
            self::SUSPENDED => 'Suspended',
            self::REVOKED => 'Access revoked',
            default => 'Unknown',
        };
    }

    /**
     * A safe message explaining why a verified account cannot enter. Never
     * reveals whether another email belongs to a staff member.
     */
    public static function denialMessage(?string $status): string
    {
        return match ($status) {
            self::INVITATION_PENDING, self::AWAITING_APPROVAL => 'Your staff access is pending approval. An administrator must approve your account before you can sign in.',
            self::LOCKED => 'This account is temporarily locked. Please try again later or contact an administrator.',
            self::DISABLED => 'This account has been disabled. Contact an administrator.',
            self::SUSPENDED => 'This account has been suspended. Contact an administrator.',
            self::REVOKED => 'Access to this portal has been revoked for this account.',
            default => 'This account is not authorized for the admin portal.',
        };
    }
}
