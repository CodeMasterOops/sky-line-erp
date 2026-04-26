<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use App\Enums\AccountingPeriodStatusEnum;

class AccountingPeriodGenerator
{
    /**
     * Create monthly accounting periods for a company and fiscal year when none exist
     * (same rules as API generate).
     */
    public static function generateForCompanyIfMissing(int $companyId, FiscalYear $fiscalYear): void
    {
        if (AccountingPeriod::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->exists()
        ) {
            return;
        }

        $startDate = Carbon::parse($fiscalYear->start_date);
        $endDate = Carbon::parse($fiscalYear->end_date);
        $current = $startDate->copy()->startOfMonth();
        $periodNumber = 1;

        while ($current->lte($endDate)) {
            $periodEnd = $current->copy()->endOfMonth();
            if ($periodEnd->gt($endDate)) {
                $periodEnd = $endDate->copy();
            }

            AccountingPeriod::create([
                'company_id' => $companyId,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $periodNumber,
                'period_name' => $current->format('F Y'),
                'start_date' => $current->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => AccountingPeriodStatusEnum::OPEN->value,
            ]);

            $current->addMonth()->startOfMonth();
            $periodNumber++;
        }
    }
}
