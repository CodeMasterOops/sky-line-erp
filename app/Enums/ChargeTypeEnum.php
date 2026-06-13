<?php

namespace App\Enums;

enum ChargeTypeEnum: string
{
    case Freight = 'freight';
    case Handling = 'handling';
    case Packaging = 'packaging';
    case Insurance = 'insurance';
    case Loading = 'loading';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Freight => 'Freight',
            self::Handling => 'Handling',
            self::Packaging => 'Packaging',
            self::Insurance => 'Insurance',
            self::Loading => 'Loading / Unloading',
            self::Other => 'Other',
        };
    }
}
