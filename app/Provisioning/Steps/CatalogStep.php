<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use Database\Seeders\CompanyCatalogSeeder;
use App\Provisioning\Contracts\ProvisioningStep;

class CatalogStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        CompanyCatalogSeeder::seedForCompany($company->id);
    }
}
