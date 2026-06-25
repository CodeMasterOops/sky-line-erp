<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\SalaryComponent;
use App\Enums\SalaryComponentTypeEnum;
use App\Provisioning\Contracts\ProvisioningStep;

class SalaryComponentsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'SalaryComponents';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.hr.salary_components') as $component) {
            $attributes = [
                'name' => $component['name'],
                'type' => SalaryComponentTypeEnum::from($component['type']),
                'is_basic' => $component['is_basic'],
                'calculation_type' => $component['calculation_type'],
                'is_taxable' => $component['is_taxable'],
                'is_active' => $component['is_active'],
                'is_system' => $component['is_system'],
            ];

            if (isset($component['percentage_base'])) {
                $attributes['percentage_base'] = $component['percentage_base'];
            }

            SalaryComponent::firstOrCreate(
                ['company_id' => $company->id, 'system_code' => $component['system_code']],
                $attributes,
            );
        }
    }
}
