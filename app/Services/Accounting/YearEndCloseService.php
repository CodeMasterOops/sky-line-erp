<?php

namespace App\Services\Accounting;

use Illuminate\Http\Request;
use App\Models\AccountingPeriod;
use Illuminate\Support\Facades\DB;
use App\Enums\AccountingPeriodStatusEnum;
use Illuminate\Validation\ValidationException;

/**
 * Guards and performs the year-end close. A fiscal year may only be closed once
 * the books are demonstrably sound: every journal balances, no approved document
 * is missing its GL posting, and the AR/AP/inventory control accounts reconcile
 * to their sub-ledgers. Closing bulk-transitions the year's accounting periods to
 * CLOSED so the PeriodLockGuard then blocks all further posting into that year.
 *
 * It deliberately does NOT auto-post a closing/appropriation entry: that requires
 * a configured retained-earnings target account, which the chart-of-accounts
 * setup does not yet model. The year's net profit is surfaced in the summary so
 * an accountant can post the appropriation entry manually if required.
 */
class YearEndCloseService
{
    public function __construct(
        private readonly AccountReportService $accountReportService,
    ) {}

    /**
     * @return array{fiscal_year_id:int, total_periods:int, open_periods:int, unbalanced_journals_count:int, unposted_documents_count:int, ar:?array, ap:?array, inventory:array, net_profit:float, checks:array<string,bool>, ready:bool}
     */
    public function readiness(int $companyId, int $fiscalYearId): array
    {
        $periodsQuery = AccountingPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId);

        $totalPeriods = (clone $periodsQuery)->count();
        $openPeriods = (clone $periodsQuery)
            ->where('status', AccountingPeriodStatusEnum::OPEN->value)
            ->count();

        $fyRequest = Request::create('/', 'GET', ['fiscal_year_id' => $fiscalYearId]);
        $plRequest = Request::create('/', 'GET', ['fiscal_year_id' => $fiscalYearId]);

        $unbalanced = $this->accountReportService->unbalancedJournals($fyRequest);
        $unposted = $this->accountReportService->unpostedDocuments($fyRequest);
        $arAp = $this->accountReportService->arApReconciliation($emptyRequest);
        $inventory = $this->accountReportService->inventoryGlReconciliation($emptyRequest);
        $profitLoss = $this->accountReportService->profitLoss($plRequest);

        $unbalancedCount = (int) ($unbalanced['summary']['count'] ?? 0);
        $unpostedCount = (int) ($unposted['summary']['total_count'] ?? 0);

        $checks = [
            'periods_generated' => $totalPeriods > 0,
            'no_unbalanced_journals' => $unbalancedCount === 0,
            'no_unposted_documents' => $unpostedCount === 0,
            'ar_reconciled' => $this->controlReconciled($arAp['ar'] ?? null),
            'ap_reconciled' => $this->controlReconciled($arAp['ap'] ?? null),
            'inventory_reconciled' => $this->controlReconciled($inventory),
        ];

        return [
            'fiscal_year_id' => $fiscalYearId,
            'total_periods' => $totalPeriods,
            'open_periods' => $openPeriods,
            'unbalanced_journals_count' => $unbalancedCount,
            'unposted_documents_count' => $unpostedCount,
            'ar' => $arAp['ar'] ?? null,
            'ap' => $arAp['ap'] ?? null,
            'inventory' => $inventory,
            'net_profit' => (float) ($profitLoss['summary']['net_profit'] ?? 0),
            'checks' => $checks,
            'ready' => ! in_array(false, $checks, true),
        ];
    }

    /**
     * @return array{fiscal_year_id:int, periods_closed:int, net_profit:float, readiness:array}
     */
    public function closeYear(int $companyId, int $fiscalYearId, int $userId): array
    {
        $readiness = $this->readiness($companyId, $fiscalYearId);

        if ($readiness['total_periods'] === 0) {
            throw ValidationException::withMessages([
                'fiscal_year_id' => [__('Generate accounting periods for this fiscal year before closing it.')],
            ]);
        }

        if (! $readiness['ready']) {
            throw ValidationException::withMessages([
                'year_end' => [$this->failureSummary($readiness['checks'])],
            ]);
        }

        $periodsClosed = DB::transaction(fn () => AccountingPeriod::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('status', AccountingPeriodStatusEnum::OPEN->value)
            ->update([
                'status' => AccountingPeriodStatusEnum::CLOSED->value,
                'closed_by' => $userId,
                'closed_at' => now(),
            ]));

        return [
            'fiscal_year_id' => $fiscalYearId,
            'periods_closed' => $periodsClosed,
            'net_profit' => $readiness['net_profit'],
            'readiness' => $readiness,
        ];
    }

    /**
     * A control account blocks the close only when it is configured AND out of
     * balance. An unconfigured account cannot be reconciled, so it is not a
     * blocker (the tenant simply isn't tracking that sub-ledger in the GL).
     *
     * @param  array{account_configured?:bool, reconciled?:bool}|null  $control
     */
    private function controlReconciled(?array $control): bool
    {
        if (! $control || ! ($control['account_configured'] ?? false)) {
            return true;
        }

        return (bool) ($control['reconciled'] ?? false);
    }

    /**
     * @param  array<string,bool>  $checks
     */
    private function failureSummary(array $checks): string
    {
        $failed = array_keys(array_filter($checks, static fn (bool $ok): bool => ! $ok));

        return __('Fiscal year is not ready to close. Resolve these checks first: :checks', [
            'checks' => implode(', ', $failed),
        ]);
    }
}
