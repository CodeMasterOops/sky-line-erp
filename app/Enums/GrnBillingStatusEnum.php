<?php

namespace App\Enums;

enum GrnBillingStatusEnum: string
{
    case OPEN = 'open';
    case PARTIALLY_BILLED = 'partially_billed';
    case FULLY_BILLED = 'fully_billed';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Open',
            self::PARTIALLY_BILLED => 'Partially Billed',
            self::FULLY_BILLED => 'Fully Billed',
        };
    }
}
