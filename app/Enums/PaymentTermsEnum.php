<?php

namespace App\Enums;

enum PaymentTermsEnum: string
{
    case Immediate = 'immediate';
    case Net7 = 'net7';
    case Net15 = 'net15';
    case Net30 = 'net30';
    case Net45 = 'net45';
    case Net60 = 'net60';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Immediate => 'Immediate',
            self::Net7 => 'Net 7 Days',
            self::Net15 => 'Net 15 Days',
            self::Net30 => 'Net 30 Days',
            self::Net45 => 'Net 45 Days',
            self::Net60 => 'Net 60 Days',
            self::Custom => 'Custom',
        };
    }

    public function creditDays(): ?int
    {
        return match ($this) {
            self::Immediate => 0,
            self::Net7 => 7,
            self::Net15 => 15,
            self::Net30 => 30,
            self::Net45 => 45,
            self::Net60 => 60,
            self::Custom => null,
        };
    }
}
