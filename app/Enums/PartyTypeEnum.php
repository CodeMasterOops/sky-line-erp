<?php

namespace App\Enums;

enum PartyTypeEnum: string
{
    case CUSTOMER = 'customer';
    case SUPPLIER = 'supplier';
    case LEAD = 'lead';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::CUSTOMER => 'Customer',
            self::SUPPLIER => 'Supplier',
            self::LEAD => 'Lead',
        };
    }
}
