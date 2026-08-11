<?php

namespace App\Modules\Gym;

use App\Models\Company;
use App\Modules\Contracts\ModuleActivator;
use App\Services\Gym\MembershipExpiryService;
use App\Services\Modules\CompanyModuleService;

/**
 * Gym module lifecycle hooks.
 *
 * `onEnable` seeds the module's per-company settings (merged over whatever the
 * company already has, so a re-enable keeps its choices) and settles the terms
 * that elapsed while the module was off.
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
        $this->seedSettings($company);
        $this->catchUpOnElapsedTerms($company);
    }

    private function seedSettings(Company $company): void
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

    /**
     * Settle the terms that ran out while the module was off.
     *
     * Time does not stop for a disabled module: a gym switched off for three
     * months comes back with memberships still marked active that expired weeks
     * ago. The nightly sweep would fix them, but not before staff had already
     * seen — and possibly acted on — a screen full of stale statuses.
     *
     * This only re-evaluates dates against today, which is exactly what the
     * sweep does; no data is created, deleted or back-dated.
     */
    private function catchUpOnElapsedTerms(Company $company): void
    {
        app(MembershipExpiryService::class)->expireDueMemberships($company);
    }

    public function onDisable(Company $company): void
    {
        // Intentionally empty — see the class docblock. Nothing about a gym's
        // data changes when the module is switched off.
    }
}
