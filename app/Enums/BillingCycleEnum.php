<?php

namespace App\Enums;

enum BillingCycleEnum: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::Monthly => 'Monthly',
            self::Yearly => 'Yearly',
        };
    }
}
