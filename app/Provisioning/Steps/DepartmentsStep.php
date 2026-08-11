<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class DepartmentsStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'Departments';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.hr.departments') as $dept) {
            Department::firstOrCreate(
                ['company_id' => $company->id, 'code' => $dept['code']],
                ['name' => $dept['name'], 'is_active' => true],
            );
        }
    }
}
