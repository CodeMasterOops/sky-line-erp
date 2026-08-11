<?php

namespace App\Modules\Contracts;

use App\Models\Company;

/**
 * Optional per-module hooks for the moment a company switches it on or off.
 *
 * `onEnable` may create default data — it must be idempotent, because a module
 * can be enabled, disabled and enabled again.
 *
 * `onDisable` must NEVER delete, archive or soft-delete tenant data. Disabling
 * a module hides it; the data stays exactly where it is so re-enabling restores
 * the module untouched. Use this hook only for reversible housekeeping.
 */
interface ModuleActivator
{
    public function onEnable(Company $company): void;

    public function onDisable(Company $company): void;
}
