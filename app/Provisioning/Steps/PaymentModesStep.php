<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\PaymentMode;
use App\Provisioning\Contracts\ProvisioningStep;

class PaymentModesStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'PaymentModes';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('company_bootstrap.default_payment_modes', []) as $mode) {
            PaymentMode::firstOrCreate(
                ['company_id' => $company->id, 'name' => $mode['name']],
                ['is_active' => $mode['is_active'] ?? true],
            );
        }
    }
}
