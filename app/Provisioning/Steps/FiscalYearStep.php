<?php

namespace App\Provisioning\Steps;

use App\Models\Branch;
use App\Models\Company;
use App\Models\FiscalYear;
use App\Provisioning\Contracts\ProvisioningStep;

class FiscalYearStep implements ProvisioningStep
{
    public function name(): string
    {
        return 'FiscalYear';
    }

    public function isIdempotent(): bool
    {
        return true;
    }

    public function run(Company $company, Branch $headOffice): void
    {
        if ($company->fiscal_year_id !== null) {
            return;
        }

        $fiscalYear = $this->resolve();
        $company->update(['fiscal_year_id' => $fiscalYear->id]);
    }

    private function resolve(): FiscalYear
    {
        $today = now()->toDateString();

        $current = FiscalYear::query()->where('is_current', true)->first();
        if ($current) {
            return $current;
        }

        $inRange = FiscalYear::query()
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->orderBy('start_date')
            ->first();
        if ($inRange) {
            return $inRange;
        }

        $year = (int) (config('company_bootstrap.fiscal_year.year') ?? now()->year);

        $existing = FiscalYear::query()
            ->where('start_date', "{$year}-01-01")
            ->where('end_date', "{$year}-12-31")
            ->first();
        if ($existing) {
            return $existing;
        }

        FiscalYear::query()->update(['is_current' => false]);

        return FiscalYear::query()->create([
            'year_name' => (string) $year,
            'year_code' => (string) $year,
            'start_date' => "{$year}-01-01",
            'end_date' => "{$year}-12-31",
            'is_current' => true,
        ]);
    }
}
