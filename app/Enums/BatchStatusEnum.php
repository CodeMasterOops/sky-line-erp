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

    /**
     * Statuses that physically hold stock that is not available to sell or issue
     * (quality holds and expiry). On-hand still counts these lots, but "available
     * to sell" must exclude them.
     */
    public function isHeld(): bool
    {
        return $this === self::Quarantine
            || $this === self::Expired
            || $this === self::Recalled;
    }

    /**
     * String values of the held statuses, for use in `whereIn` constraints.
     *
     * @return array<int, string>
     */
    public static function heldValues(): array
    {
        return [
            self::Quarantine->value,
            self::Expired->value,
            self::Recalled->value,
        ];
    }

    /**
     * Whether an operator may manually move a lot from this status to the target.
     *
     * Allowed: Active ↔ Quarantine, and Active/Quarantine → Recalled. Expired and
     * Depleted are system-managed (the expiry job / ledger reconciliation set them and
     * they are never set by hand), and Recalled is terminal — a recalled lot is cleared
     * only by writing it off, not by flipping it back to a sellable status.
     */
    public function canTransitionTo(self $to): bool
    {
        if ($this === $to) {
            return true;
        }

        return match ($this) {
            self::Active => $to === self::Quarantine || $to === self::Recalled,
            self::Quarantine => $to === self::Active || $to === self::Recalled,
            default => false,
        };
    }
}
