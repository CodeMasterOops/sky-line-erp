<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use App\Models\FiscalYear;
use App\Models\AccountingPeriod;
use App\Enums\AccountingPeriodStatusEnum;
use App\Services\Nepal\NepaliDateService;

class AccountingPeriodGenerator
{
    /**
     * Create one accounting period per Bikram Sambat (BS) month for a company and
     * fiscal year when none exist (same rules as API generate).
     *
     * The fiscal year is stored as AD dates but represents a Nepali fiscal year,
     * so periods are sliced on real BS month boundaries (e.g. Shrawan 1 → Shrawan
     * end) rather than AD calendar months. The first and last periods are clamped
     * to the fiscal year's start/end so partial BS months are never exceeded.
     */
    public static function generateForCompanyIfMissing(int $companyId, FiscalYear $fiscalYear): void
    {
        if (AccountingPeriod::where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYear->id)
            ->exists()
        ) {
            return;
        }

        $nepaliDate = new NepaliDateService;

        $startDate = Carbon::parse($fiscalYear->start_date)->startOfDay();
        $endDate = Carbon::parse($fiscalYear->end_date)->startOfDay();

        $start = $nepaliDate->adToBs($startDate);
        $bsYear = $start['year'];
        $bsMonth = $start['month'];
        $periodNumber = 1;

        while (true) {
            $monthStart = $nepaliDate->bsToAd($bsYear, $bsMonth, 1);

            if ($monthStart->gt($endDate)) {
                break;
            }

            $monthEnd = $nepaliDate->bsToAd($bsYear, $bsMonth, $nepaliDate->daysInBsMonth($bsYear, $bsMonth));

            $periodStart = $monthStart->lt($startDate) ? $startDate->copy() : $monthStart;
            $periodEnd = $monthEnd->gt($endDate) ? $endDate->copy() : $monthEnd;

            AccountingPeriod::create([
                'company_id' => $companyId,
                'fiscal_year_id' => $fiscalYear->id,
                'period_number' => $periodNumber,
                'period_name' => $nepaliDate->monthName($bsMonth).' '.$bsYear,
                'start_date' => $periodStart->toDateString(),
                'end_date' => $periodEnd->toDateString(),
                'status' => AccountingPeriodStatusEnum::OPEN->value,
            ]);

            $bsMonth++;
            if ($bsMonth > 12) {
                $bsMonth = 1;
                $bsYear++;
            }
            $periodNumber++;
        }
    }
}
