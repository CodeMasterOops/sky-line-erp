<?php

namespace App\Console\Concerns;

use App\Services\Modules\CompanyModuleService;

/**
 * The standard way for a module-owned scheduled command to stop working for
 * companies that no longer run its module.
 *
 * `routes/console.php` keeps registering the schedule unconditionally — the
 * command filters, not the scheduler (plan §3.7 item 3). Every command listed
 * in a module's `scheduled_commands` must consult this; `ModuleRegistryTest`
 * fails the build when one does not.
 */
trait SkipsDisabledCompanies
{
    /**
     * The ids of the companies still running the given module.
     *
     * @return list<int>
     */
    protected function companiesWithModule(string $moduleKey): array
    {
        return app(CompanyModuleService::class)->companyIdsWith($moduleKey);
    }

    /**
     * Report the skip in a uniform way, so an empty run reads as a deliberate
     * no-op rather than a silent failure.
     *
     * @param  list<int>  $companyIds
     */
    protected function reportModuleScope(string $moduleKey, array $companyIds): bool
    {
        if ($companyIds === []) {
            $this->info("No company has the [{$moduleKey}] module enabled — nothing to do.");

            return false;
        }

        return true;
    }
}
