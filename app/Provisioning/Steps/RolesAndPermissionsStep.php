<?php

namespace App\Provisioning\Steps;

use App\Models\Role;
use App\Models\Branch;
use App\Models\Company;
use App\Provisioning\Contracts\ProvisioningStep;

class RolesAndPermissionsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'RolesAndPermissions';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.roles') as $definition) {
            Role::firstOrCreate(
                ['company_id' => $company->id, 'name' => $definition['name']],
                ['permissions' => $definition['permissions']],
            );
        }
    }
}
