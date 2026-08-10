<?php

namespace App\Observers;

use App\Models\CompanyModule;
use App\Services\Modules\ModuleCache;

/**
 * Keeps the per-company module cache honest. The service already forgets the
 * cache after its own writes; this catches everything else — seeders, imports,
 * tinker sessions, future admin screens — so a company can never keep serving a
 * stale module set after its row changes.
 *
 * Registered through event strings in AppServiceProvider rather than
 * CompanyModule::observe(), because observe() instantiates the model at boot
 * time and would run bootMultiTenant() before test migrations exist.
 */
class CompanyModuleObserver
{
    public function __construct(private readonly ModuleCache $cache) {}

    public function saved(CompanyModule $module): void
    {
        $this->flush($module);
    }

    public function deleted(CompanyModule $module): void
    {
        $this->flush($module);
    }

    private function flush(CompanyModule $module): void
    {
        if ($module->company_id) {
            $this->cache->forget((int) $module->company_id);
        }
    }
}
