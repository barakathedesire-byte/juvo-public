<?php

namespace App\Support\Access;

use App\Models\User;

/**
 * Record-level access scope attached to a staff account. Permissions decide
 * *what* a staff member can do; scope decides *which records* those actions
 * apply to. Scope must be enforced on backend queries, single-record
 * retrieval, searches and exports — never by filtering the frontend.
 *
 * Stored on `users.access_scope` as JSON:
 *   { "type": "platform|department|vendors|tickets|territory|own",
 *     "ids": [1,2,3],
 *     "department_id": 2,
 *     "read_only": true }
 */
class AccessScope
{
    public const PLATFORM = 'platform';

    public const DEPARTMENT = 'department';

    public const VENDORS = 'vendors';

    public const TICKETS = 'tickets';

    public const TERRITORY = 'territory';

    public const OWN = 'own';

    public function __construct(
        public readonly string $type = self::PLATFORM,
        public readonly array $ids = [],
        public readonly ?int $departmentId = null,
        public readonly bool $readOnly = false,
    ) {}

    public static function for(?User $user): self
    {
        $raw = $user?->access_scope ?? null;

        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: null;
        }

        if (! is_array($raw) || empty($raw['type'])) {
            // No scope configured: platform-wide (permissions still apply).
            return new self(self::PLATFORM, [], null, false);
        }

        $type = in_array($raw['type'], self::types(), true) ? $raw['type'] : self::PLATFORM;

        return new self(
            $type,
            array_map('intval', (array) ($raw['ids'] ?? [])),
            isset($raw['department_id']) ? (int) $raw['department_id'] : null,
            (bool) ($raw['read_only'] ?? false),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function types(): array
    {
        return [self::PLATFORM, self::DEPARTMENT, self::VENDORS, self::TICKETS, self::TERRITORY, self::OWN];
    }

    public function isPlatform(): bool
    {
        return $this->type === self::PLATFORM;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    /**
     * Whether a specific vendor record is inside scope.
     */
    public function allowsVendor(?int $vendorId): bool
    {
        if ($this->type !== self::VENDORS) {
            return true;
        }

        return $vendorId !== null && in_array($vendorId, $this->ids, true);
    }

    /**
     * Whether a ticket assigned to $assigneeId is inside scope.
     */
    public function allowsTicket(?int $assigneeId, ?int $viewerId): bool
    {
        if (! in_array($this->type, [self::TICKETS, self::OWN], true)) {
            return true;
        }

        return $assigneeId !== null && $assigneeId === $viewerId;
    }

    /**
     * Whether a record owned by $ownerId is inside scope for $viewerId.
     * Used for "own records only" and department-scoped records.
     */
    public function allowsOwner(?int $ownerId, ?int $viewerId): bool
    {
        if (! in_array($this->type, [self::OWN, self::TICKETS], true)) {
            return true;
        }

        return $ownerId !== null && $ownerId === $viewerId;
    }

    public function allowsDepartment(?int $departmentId): bool
    {
        if ($this->type !== self::DEPARTMENT) {
            return true;
        }

        return $this->departmentId !== null && $departmentId === $this->departmentId;
    }

    /**
     * True when the scope narrows a query by a set of ids.
     */
    public function isLimitedToIds(): bool
    {
        return in_array($this->type, [self::VENDORS, self::TERRITORY], true);
    }
}
