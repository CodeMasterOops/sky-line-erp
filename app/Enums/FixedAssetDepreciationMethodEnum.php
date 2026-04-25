<?php

namespace App\Enums;

enum FixedAssetDepreciationMethodEnum: string
{
    case SLM = 'slm';
    case WDV = 'wdv';

    public function label(): string
    {
        return match ($this) {
            self::SLM => 'Straight Line Method (SLM)',
            self::WDV => 'Written Down Value (WDV)',
        };
    }
}
