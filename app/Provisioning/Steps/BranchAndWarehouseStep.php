<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Warehouse;
use App\Provisioning\Contracts\ProvisioningStep;

class BranchAndWarehouseStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'BranchAndWarehouse';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $whCfg = config('company_bootstrap.default_warehouse');

        // Warehouse must be linked to the head-office branch (branch-owned per isolation plan).
        Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => $whCfg['code']],
            ['name' => $whCfg['name'], 'branch_id' => $headOffice->id],
        );
    }
}
