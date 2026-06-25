<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Designation;
use App\Provisioning\Contracts\ProvisioningStep;

class DesignationsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'Designations';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.hr.designations') as $desig) {
            Designation::firstOrCreate(
                ['company_id' => $company->id, 'name' => $desig['name']],
                ['is_active' => true],
            );
        }
    }
}
