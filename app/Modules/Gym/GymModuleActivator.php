<?php

namespace App\Modules\Gym;

use App\Models\Company;
use App\Modules\Contracts\ModuleActivator;
use App\Services\Modules\CompanyModuleService;

/**
 * Gym module lifecycle hooks.
 *
 * `onEnable` seeds the module's per-company settings (merged over whatever the
 * company already has, so a re-enable keeps its choices).
 *
 * `onDisable` deliberately does nothing. Members, plans and every invoice
 * raised against them stay exactly where they are — switching the module off
 * closes the doors, and switching it on again finds the room as it was left.
 */
class GymModuleActivator implements ModuleActivator
{
    public function __construct(private readonly CompanyModuleService $modules) {}

    public function onEnable(Company $company): void
    {
        $defaults = config('provisioning.gym.module_settings', []);

        if ($defaults === []) {
            return;
        }

        $existing = $this->modules->settingsFor((int) $company->id, 'gym');

        $this->modules->updateSettings(
            $company,
            'gym',
            array_merge($defaults, $existing),
        );
    }

    public function onDisable(Company $company): void
    {
        // Intentionally empty — see the class docblock. Nothing about a gym's
        // data changes when the module is switched off.
    }
}
