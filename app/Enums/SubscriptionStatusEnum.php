<?php

namespace App\Enums;

enum SubscriptionStatusEnum: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::Trialing => 'Trialing',
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    /**
     * @return list<string>
     */
    public static function activeStatuses(): array
    {
        return [
            self::Trialing->value,
            self::Active->value,
        ];
    }
}
