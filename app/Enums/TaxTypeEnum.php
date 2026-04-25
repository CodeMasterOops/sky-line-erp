<?php

namespace App\Enums;

enum TaxTypeEnum: string
{
    case VAT_STANDARD = 'vat_standard';
    case VAT_EXEMPT = 'vat_exempt';
    case VAT_ZERO_RATED = 'vat_zero_rated';
    case TDS = 'tds';

    public function label(): string
    {
        return match ($this) {
            self::VAT_STANDARD => 'VAT Standard (13%)',
            self::VAT_EXEMPT => 'VAT Exempt',
            self::VAT_ZERO_RATED => 'VAT Zero Rated',
            self::TDS => 'TDS (Tax Deducted at Source)',
        };
    }

    public function isVat(): bool
    {
        return in_array($this, [self::VAT_STANDARD, self::VAT_EXEMPT, self::VAT_ZERO_RATED]);
    }

    public function isTds(): bool
    {
        return $this === self::TDS;
    }
}
