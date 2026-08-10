<?php

namespace App\Enums;

/**
 * The append-only audit vocabulary for company_module_events. Every change to a
 * company's module state writes one of these with the actor who caused it.
 */
enum ModuleEventActionEnum: string
{
    case Enabled = 'enabled';
    case Disabled = 'disabled';
    case SettingsUpdated = 'settings_updated';
    case CategoryApplied = 'category_applied';
    case PlanReconciled = 'plan_reconciled';

    public function label(): string
    {
        return match ($this) {
            self::Enabled => 'Enabled',
            self::Disabled => 'Disabled',
            self::SettingsUpdated => 'Settings updated',
            self::CategoryApplied => 'Category defaults applied',
            self::PlanReconciled => 'Reconciled with plan',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
