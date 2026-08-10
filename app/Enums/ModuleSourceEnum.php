<?php

namespace App\Enums;

/**
 * Why a company_modules row exists. Recorded so the Super Admin matrix can show
 * whether a module is on because of the company's category, a deliberate manual
 * override, its subscription plan, or the one-off modularity backfill.
 */
enum ModuleSourceEnum: string
{
    case Core = 'core';
    case Category = 'category';
    case Manual = 'manual';
    case Plan = 'plan';
    case Migration = 'migration';
    case Unconfigured = 'unconfigured';

    public function label(): string
    {
        return match ($this) {
            self::Core => 'Always on',
            self::Category => 'Category default',
            self::Manual => 'Manual override',
            self::Plan => 'Plan entitlement',
            self::Migration => 'Migrated',
            self::Unconfigured => 'Not configured yet',
        };
    }

    /**
     * A manual row is the Super Admin speaking directly, so it survives plan
     * reconciliation instead of being reset by it.
     */
    public function isExplicitOverride(): bool
    {
        return $this === self::Manual;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
