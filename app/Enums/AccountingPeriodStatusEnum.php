<?php

namespace App\Enums;

enum AccountingPeriodStatusEnum: string
{
    case OPEN = 'open';
    case CLOSED = 'closed';
    case LOCKED = 'locked';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::CLOSED => 'Closed',
            self::LOCKED => 'Locked',
        };
    }
}
