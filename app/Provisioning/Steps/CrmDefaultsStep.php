<?php

namespace App\Provisioning\Steps;

use App\Models\Tag;
use App\Models\Branch;
use App\Models\Company;
use App\Provisioning\Contracts\ProvisioningStep;

class CrmDefaultsStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'CrmDefaults';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.crm.lead_source_tags') as $tag) {
            Tag::firstOrCreate(
                ['company_id' => $company->id, 'name' => $tag['name']],
                ['color' => $tag['color']],
            );
        }
    }
}
