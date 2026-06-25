<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use Database\Seeders\TaxSeeder;
use App\Provisioning\Contracts\ProvisioningStep;

class TaxConfigStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'TaxConfig';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        TaxSeeder::seedForCompany($company->id);
    }
}
