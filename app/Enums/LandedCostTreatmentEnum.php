<?php

namespace App\Enums;

enum LandedCostTreatmentEnum: string
{
    case Capitalized = 'capitalized';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Capitalized => 'Capitalized',
            self::Expense => 'Expense',
        };
    }
}
