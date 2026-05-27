<?php

namespace App\Enums;

enum LandedCostAllocationMethodEnum: string
{
    case Value = 'value';
    case Quantity = 'quantity';
    case Equal = 'equal';

    public function label(): string
    {
        return match ($this) {
            self::Value => 'By value',
            self::Quantity => 'By quantity',
            self::Equal => 'Equally',
        };
    }
}
