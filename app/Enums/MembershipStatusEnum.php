<?php

namespace App\Enums;

/**
 * The life of one membership term.
 *
 *   pending ──(activate)──▶ active ──(end date + grace passed)──▶ expired
 *      │                       │
 *      └──(cancel)─────────────┴──(cancel)──▶ cancelled
 *
 * `frozen` is reached from active and returns to it (Phase 7).
 */
enum MembershipStatusEnum: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Frozen = 'frozen';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Active => 'Active',
            self::Expired => 'Expired',
            self::Cancelled => 'Cancelled',
            self::Frozen => 'Frozen',
        };
    }

    /**
     * Statuses that occupy a member's "current membership" slot, and so block a
     * second one while `allow_multiple_active_memberships` is off.
     *
     * @return list<self>
     */
    public static function occupyingStatuses(): array
    {
        return [self::Pending, self::Active, self::Frozen];
    }

    /**
     * @return list<string>
     */
    public static function occupyingValues(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::occupyingStatuses());
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Expired, self::Cancelled], true);
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => ['id' => $case->value, 'name' => $case->label()],
            self::cases(),
        );
    }
}
