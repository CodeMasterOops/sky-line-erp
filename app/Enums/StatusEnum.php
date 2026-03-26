<?php

namespace App\Enums;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
        };
    }
}
