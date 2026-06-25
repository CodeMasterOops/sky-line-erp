<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Provisioning\Contracts\ProvisioningStep;
use App\Services\Accounting\AccountingPeriodGenerator;

class AccountingPeriodsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'AccountingPeriods';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $company->refresh();

        if ($company->fiscal_year_id === null) {
            return;
        }

        $fiscalYear = FiscalYear::query()->findOrFail($company->fiscal_year_id);
        AccountingPeriodGenerator::generateForCompanyIfMissing($company->id, $fiscalYear);
    }
}
