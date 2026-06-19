<?php

namespace App\Enums;

enum BatchStatusEnum: string
{
    case Active = 'active';
    case Quarantine = 'quarantine';
    case Expired = 'expired';
    case Depleted = 'depleted';
    case Recalled = 'recalled';

    public function label(): string
    {
        return self::getLabel($this);
    }

    public static function getLabel(self $value): string
    {
        return match ($value) {
            self::Active => 'Active',
            self::Quarantine => 'Quarantine',
            self::Expired => 'Expired',
            self::Depleted => 'Depleted',
            self::Recalled => 'Recalled',
        };
    }

    /**
     * Statuses that may be issued/consumed out of stock.
     */
    public function isIssuable(): bool
    {
        return $this === self::Active;
    }

    /**
     * Statuses that are managed automatically from on-hand quantity and may be
     * flipped by reconciliation. Manual quality holds (quarantine, recalled) and
     * expiry are preserved across reconciliation.
     */
    public function isAutoManaged(): bool
    {
        return $this === self::Active || $this === self::Depleted;
    }
}
