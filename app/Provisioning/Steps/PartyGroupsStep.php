<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PartyGroup;
use App\Provisioning\Contracts\ProvisioningStep;

class PartyGroupsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'PartyGroups';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.party_groups') as $group) {
            PartyGroup::firstOrCreate(
                ['company_id' => $company->id, 'code' => $group['code']],
                [
                    'type' => $group['type'],
                    'name' => $group['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
