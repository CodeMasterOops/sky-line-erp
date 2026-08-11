<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Setting;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class ManufacturingDefaultsStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'ManufacturingDefaults';
    }

    public function module(): string
    {
        return 'manufacturing';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.manufacturing') as $key => $value) {
            $settingKey = "company.{$company->id}.manufacturing.{$key}";

            Setting::updateOrCreate(
                ['key' => $settingKey],
                ['value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value],
            );
        }
    }
}
