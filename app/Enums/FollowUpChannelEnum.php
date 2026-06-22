<?php

namespace App\Enums;

enum FollowUpChannelEnum: string
{
    case Call = 'call';
    case Email = 'email';
    case Meeting = 'meeting';
    case Visit = 'visit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::Email => 'Email',
            self::Meeting => 'Meeting',
            self::Visit => 'Visit',
            self::Other => 'Other',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
