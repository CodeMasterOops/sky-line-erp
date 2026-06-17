<?php

namespace App\Enums;

enum PayrollStatusEnum: string
{
    case DRAFT = 'draft';
    case PENDING_APPROVAL = 'pending_approval';
    case PROCESSED = 'processed';
    case PAID = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_APPROVAL => 'Pending Approval',
            self::PROCESSED => 'Processed',
            self::PAID => 'Paid',
        };
    }
}
