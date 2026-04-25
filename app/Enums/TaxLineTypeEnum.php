<?php

namespace App\Enums;

enum TaxLineTypeEnum: string
{
    case TAXABLE = 'taxable';
    case EXEMPT = 'exempt';
    case ZERO_RATED = 'zero_rated';

    public function label(): string
    {
        return match ($this) {
            self::TAXABLE => 'Taxable (13% VAT)',
            self::EXEMPT => 'Exempt',
            self::ZERO_RATED => 'Zero Rated',
        };
    }
}
