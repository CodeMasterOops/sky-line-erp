<?php

namespace App\Enums;

enum StockDirectionEnum: string
{
    case IN = 'in';
    case OUT = 'out';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::IN => 'In',
            self::OUT => 'Out',
        };
    }
}
