<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Provisioning\Contracts\ProvisioningStep;

class CompanyPreferencesStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'CompanyPreferences';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.preferences') as $key => $value) {
            $settingKey = "company.{$company->id}.{$key}";

            Setting::updateOrCreate(
                ['key' => $settingKey],
                ['value' => is_array($value) ? json_encode($value) : (string) $value],
            );
        }
    }
}
