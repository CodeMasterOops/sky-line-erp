<?php

namespace App\Provisioning\Steps;

use Carbon\Carbon;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Holiday;
use App\Provisioning\Contracts\ProvisioningStep;

class HolidaysStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'Holidays';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        $year = now()->year;

        if (Holiday::where('company_id', $company->id)->whereYear('date', $year)->exists()) {
            return;
        }

        foreach (config('provisioning.hr.public_holidays') as $holiday) {
            $date = Carbon::createFromDate($year, $holiday['month'], $holiday['day']);

            Holiday::create([
                'company_id' => $company->id,
                'name' => $holiday['name'],
                'date' => $date->toDateString(),
            ]);
        }
    }
}
