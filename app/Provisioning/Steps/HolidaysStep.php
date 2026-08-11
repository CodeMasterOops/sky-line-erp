<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Holiday;
use Spatie\Holidays\Holidays;
use Spatie\Holidays\Countries\Nepal;
use App\Provisioning\Contracts\ModuleAwareStep;
use App\Provisioning\Contracts\ProvisioningStep;

class HolidaysStep implements ModuleAwareStep, ProvisioningStep
{
    public function name(): string
    {
        return 'Holidays';
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
        $year = now()->year;

        if (Holiday::query()->where('company_id', $company->id)->whereYear('date', $year)->exists()) {
            return;
        }

        $holidays = Holidays::for(Nepal::make(), $year)->get();

        // spatie/holidays uses per-year lookup tables for BS/lunar holidays.
        // When data isn't yet available for the current year only the two fixed
        // Gregorian entries are returned. Fall back to the static config list so
        // that new companies always get a reasonable set of public holidays.
        if (count($holidays) <= 2) {
            foreach (config('provisioning.hr.public_holidays') as $entry) {
                Holiday::firstOrCreate(
                    ['company_id' => $company->id, 'date' => "{$year}-".str_pad($entry['month'], 2, '0', STR_PAD_LEFT).'-'.str_pad($entry['day'], 2, '0', STR_PAD_LEFT)],
                    ['name' => $entry['name']],
                );
            }

            return;
        }

        foreach ($holidays as $holiday) {
            Holiday::firstOrCreate(
                ['company_id' => $company->id, 'date' => $holiday->date->toDateString()],
                ['name' => $holiday->name],
            );
        }
    }
}
