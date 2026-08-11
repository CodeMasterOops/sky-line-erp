<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\LeaveType;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class LeaveTypesStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'LeaveTypes';
    }

    public function module(): string
    {
        return 'hr';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        foreach (config('provisioning.hr.leave_types') as $type) {
            LeaveType::firstOrCreate(
                ['company_id' => $company->id, 'name' => $type['name']],
                [
                    'days_allowed' => $type['days_allowed'],
                    'is_paid' => $type['is_paid'],
                    'is_active' => true,
                ],
            );
        }
    }
}
