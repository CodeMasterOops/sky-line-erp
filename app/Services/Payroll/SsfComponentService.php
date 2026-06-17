<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\SalaryComponent;
use App\Enums\SalaryComponentTypeEnum;

/**
 * Ensures the two mandatory SSF salary components exist for a company.
 *
 * SSF (Social Security Fund) Nepal:
 *   - Employee contribution: 11% of basic salary (DEDUCTION)
 *   - Employer contribution: 20% of basic salary (DEDUCTION on payroll cost)
 */
class SsfComponentService
{
    private const EMPLOYEE_CODE = 'SSF_EMPLOYEE_11';

    private const EMPLOYER_CODE = 'SSF_EMPLOYER_20';

    public function ensure(Company $company): void
    {
        SalaryComponent::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'system_code' => self::EMPLOYEE_CODE],
            [
                'name' => 'SSF Employee Contribution (11%)',
                'type' => SalaryComponentTypeEnum::DEDUCTION,
                'calculation_type' => 'percentage',
                'is_taxable' => false,
                'is_active' => true,
                'is_system' => true,
            ],
        );

        SalaryComponent::withoutGlobalScopes()->firstOrCreate(
            ['company_id' => $company->id, 'system_code' => self::EMPLOYER_CODE],
            [
                'name' => 'SSF Employer Contribution (20%)',
                'type' => SalaryComponentTypeEnum::DEDUCTION,
                'calculation_type' => 'percentage',
                'is_taxable' => false,
                'is_active' => true,
                'is_system' => true,
            ],
        );
    }
}
