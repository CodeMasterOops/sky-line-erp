<?php

namespace App\Enums;

enum StatusEnum: string
{
    case DRAFT = 'draft';
    case APPROVED = 'approved';
    case IN_TRANSIT = 'in_transit';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::DRAFT => 'Draft',
            self::APPROVED => 'Approved',
            self::IN_TRANSIT => 'In Transit',
        };
    }
}
