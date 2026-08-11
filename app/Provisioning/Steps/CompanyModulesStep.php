<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CompanyCategory;
use App\Services\Modules\CompanyModuleService;
use App\Provisioning\Contracts\ProvisioningStep;

/**
 * Writes the company's module set before any module-owned step runs.
 *
 * A company that picked no industry falls back to the catalogue's default
 * category, so every provisioned company ends up with explicit, auditable rows
 * rather than relying on the resolver's pre-modular fallback.
 *
 * Must be the first step in the pipeline: the steps after it are skipped based
 * on what this one decides.
 */
class CompanyModulesStep implements ProvisioningStep
{
    public function __construct(private readonly CompanyModuleService $modules) {}

    public function name(): string
    {
        return 'CompanyModules';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        if (! $company->company_category_id) {
            $default = CompanyCategory::query()->default()->active()->first();

            if ($default) {
                $company->forceFill(['company_category_id' => $default->id])->save();
                $company->unsetRelation('category');
            }
        }

        $this->modules->materializeFor($company);
    }
}
