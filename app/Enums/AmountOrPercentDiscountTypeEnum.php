<?php

namespace App\Enums;

enum AmountOrPercentDiscountTypeEnum: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public static function tryFromString(?string $value): self
    {
        if ($value === null || $value === '') {
            return self::Fixed;
        }

        return self::tryFrom($value) ?? self::Fixed;
    }
}
