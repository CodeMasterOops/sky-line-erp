<?php

namespace App\Enums;

enum PayrollStatusEnum: string
{
    case DRAFT = 'draft';
    case PROCESSED = 'processed';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PROCESSED => 'Processed',
            self::PAID => 'Paid',
        };
    }
}
