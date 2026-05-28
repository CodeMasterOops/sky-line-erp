<?php

namespace App\Services\Accounting;

use Carbon\Carbon;
use App\Models\AccountingPeriod;
use Illuminate\Validation\ValidationException;

/**
 * Blocks GL posting into a closed or locked accounting period. Called from
 * every posting chokepoint (sales, purchase, expense, credit-note, debit-note)
 * just before the journal is written, so an accountant can confidently close a
 * period and have the books stay closed.
 *
 * If no period is defined that covers the document date (e.g. a tenant that
 * hasn't generated periods yet), posting is allowed — the feature is opt-in
 * via period generation, so it can't silently block legacy/uninstalled tenants.
 */
class PeriodLockGuard
{
    /**
     * @param  string|\DateTimeInterface|null  $date  Document date being posted.
     *
     * @throws ValidationException when the date falls within a closed/locked period.
     */
    public function assertPostable(int $companyId, ?int $fiscalYearId, mixed $date): void
    {
        if ($date === null) {
            return;
        }

        $period = $this->findPeriodFor($companyId, $fiscalYearId, $date);

        if ($period && $period->isClosed()) {
            throw ValidationException::withMessages([
                'period' => sprintf(
                    'Cannot post into %s — the accounting period is %s. Re-open the period to post entries dated %s.',
                    $period->period_name,
                    $period->status->label(),
                    $this->dateString($date),
                ),
            ]);
        }
    }

    protected function findPeriodFor(int $companyId, ?int $fiscalYearId, mixed $date): ?AccountingPeriod
    {
        $dateString = $this->dateString($date);

        return AccountingPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->when($fiscalYearId, fn ($q) => $q->where('fiscal_year_id', $fiscalYearId))
            ->where('start_date', '<=', $dateString)
            ->where('end_date', '>=', $dateString)
            ->first();
    }

    protected function dateString(mixed $date): string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return Carbon::parse((string) $date)->toDateString();
    }
}
