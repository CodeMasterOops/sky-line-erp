<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Services\Accounting\CoaInsertService;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class ChartOfAccountsStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'ChartOfAccounts';
    }

    public function module(): string
    {
        return 'accounting';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        (new CoaInsertService($company))->saveCoaData();
    }
}
