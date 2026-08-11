<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\WorkSchedule;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class WorkScheduleStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'WorkSchedule';
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
        $cfg = config('provisioning.hr.work_schedule');

        WorkSchedule::firstOrCreate(
            ['company_id' => $company->id, 'name' => $cfg['name']],
            [
                'start_time' => $cfg['start_time'],
                'end_time' => $cfg['end_time'],
                'grace_minutes' => $cfg['grace_minutes'],
                'standard_hours_per_day' => $cfg['standard_hours_per_day'],
                'overtime_multiplier' => $cfg['overtime_multiplier'],
                'overtime_enabled' => $cfg['overtime_enabled'],
                'weekly_off_days' => $cfg['weekly_off_days'],
                'is_default' => $cfg['is_default'],
            ],
        );
    }
}
