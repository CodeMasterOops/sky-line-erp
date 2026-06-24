<?php

namespace App\Enums;

enum MaritalStatusEnum: string
{
    case Single = 'single';
    case Married = 'married';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single',
            self::Married => 'Married',
        };
    }
}
