<?php

namespace App\Enums;

/**
 * A gym member's standing. Denormalised from the member's latest membership so
 * lists can filter on it cheaply; kept in step by MemberStatusSynchroniser
 * (Phase 6) rather than edited by hand.
 */
enum MemberStatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Expired = 'expired';
    case Frozen = 'frozen';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Expired => 'Expired',
            self::Frozen => 'Frozen',
            self::Cancelled => 'Cancelled',
        };
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
