<?php

namespace App\Services\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\FiscalYear;
use App\Models\JournalItem;
use App\Models\AccountGroup;
use App\Enums\JournalTypeEnum;
use Illuminate\Support\Facades\DB;

/**
 * Posts the year-end closing entry: zeroes every nominal (income/expense)
 * account by reversing its net balance for the fiscal year, transferring the
 * net result to the configured retained-earnings equity account.
 *
 * The journal balances by construction — reversing each nominal account's net
 * leaves a residual equal to the net profit/loss, which the retained-earnings
 * line absorbs (CR for a profit, DR for a loss). Idempotent: a fiscal year that
 * already has a CLOSING_ENTRY journal is a no-op.
 */
class ClosingEntryService
{
    public function __construct(
        private readonly JournalBalanceGuard $balanceGuard,
    ) {}

    public function alreadyPosted(int $companyId, int $fiscalYearId): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('fiscal_year_id', $fiscalYearId)
            ->where('type', JournalTypeEnum::CLOSING_ENTRY->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @return array{posted:bool, journal_id:?int, net_profit:float, accounts_closed:int, reason:?string}
     */
    public function post(int $companyId, FiscalYear $fiscalYear, int $retainedEarningsAccountId, int $userId): array
    {
        $result = [
            'posted' => false,
            'journal_id' => null,
            'net_profit' => 0.0,
            'accounts_closed' => 0,
            'reason' => null,
        ];

        if ($this->alreadyPosted($companyId, $fiscalYear->id)) {
            return [...$result, 'reason' => 'already_posted'];
        }

        $nominalAccountIds = $this->nominalAccountIds($companyId);
        if ($nominalAccountIds === []) {
            return [...$result, 'reason' => 'no_nominal_accounts'];
        }

        $startDate = $fiscalYear->start_date->toDateString();
        $endDate = $fiscalYear->end_date->toDateString();

        $balances = $this->accountNetBalances($companyId, $nominalAccountIds, $startDate, $endDate);

        $lines = [];
        $totalDr = 0.0;
        $totalCr = 0.0;

        foreach ($balances as $accountId => $net) {
            $net = round((float) $net, 2);
            if (abs($net) < 0.005) {
                continue;
            }

            if ($net > 0) {
                // Debit-balance account (e.g. expense): credit it to zero.
                $lines[] = ['account_id' => $accountId, 'dr_amount' => 0, 'cr_amount' => $net];
                $totalCr += $net;
            } else {
                // Credit-balance account (e.g. income): debit it to zero.
                $lines[] = ['account_id' => $accountId, 'dr_amount' => -$net, 'cr_amount' => 0];
                $totalDr += -$net;
            }
        }

        if ($lines === []) {
            return [...$result, 'reason' => 'nothing_to_close'];
        }

        // Residual after reversing the nominal accounts = income − expense.
        $netProfit = round($totalDr - $totalCr, 2);
        $closedCount = count($lines);

        if ($netProfit > 0) {
            $lines[] = ['account_id' => $retainedEarningsAccountId, 'dr_amount' => 0, 'cr_amount' => $netProfit];
        } elseif ($netProfit < 0) {
            $lines[] = ['account_id' => $retainedEarningsAccountId, 'dr_amount' => -$netProfit, 'cr_amount' => 0];
        }

        $journal = DB::transaction(function () use ($companyId, $fiscalYear, $endDate, $userId, $lines) {
            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'fiscal_year_id' => $fiscalYear->id,
                'type' => JournalTypeEnum::CLOSING_ENTRY,
                'reference_type' => null,
                'reference_id' => null,
                'voucher_no' => 'CLOSE-JV-'.$fiscalYear->id,
                'date' => $endDate,
                'remarks' => __('Year-end closing entry for :year', ['year' => $fiscalYear->year_name]),
                'create_user_id' => $userId,
                'approve_user_id' => $userId,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            foreach ($lines as $line) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $line['account_id'],
                    'dr_amount' => $line['dr_amount'],
                    'cr_amount' => $line['cr_amount'],
                ]);
            }

            $this->balanceGuard->assertBalanced($journal);

            return $journal;
        });

        return [
            'posted' => true,
            'journal_id' => $journal->id,
            'net_profit' => $netProfit,
            'accounts_closed' => $closedCount,
            'reason' => null,
        ];
    }

    /**
     * Account IDs under the income and expenses root groups (recursively).
     *
     * @return list<int>
     */
    private function nominalAccountIds(int $companyId): array
    {
        $rootGroups = AccountGroup::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereNull('parent_id')
            ->with(['accounts', 'childrenRecursive'])
            ->get()
            ->filter(fn (AccountGroup $group) => in_array(strtolower($group->name), ['income', 'expenses'], true));

        $ids = [];
        foreach ($rootGroups as $group) {
            $this->collectAccountIds($group, $ids);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $ids
     */
    private function collectAccountIds(AccountGroup $group, array &$ids): void
    {
        foreach ($group->accounts as $account) {
            $ids[] = $account->id;
        }

        foreach ($group->childrenRecursive as $child) {
            $this->collectAccountIds($child, $ids);
        }
    }

    /**
     * Net (dr − cr) per account over the fiscal year, from approved,
     * non-deleted journals.
     *
     * @param  list<int>  $accountIds
     * @return array<int, float>
     */
    private function accountNetBalances(int $companyId, array $accountIds, string $startDate, string $endDate): array
    {
        return JournalItem::query()
            ->select('journal_items.account_id')
            ->selectRaw('SUM(journal_items.dr_amount - journal_items.cr_amount) as net')
            ->join('journals', 'journals.id', '=', 'journal_items.journal_id')
            ->whereIn('journal_items.account_id', $accountIds)
            ->where('journals.company_id', $companyId)
            ->where('journals.status', StatusEnum::APPROVED->value)
            ->whereNull('journals.deleted_at')
            ->whereNull('journal_items.deleted_at')
            ->whereBetween('journals.date', [$startDate, $endDate])
            ->groupBy('journal_items.account_id')
            ->pluck('net', 'journal_items.account_id')
            ->map(fn ($net) => (float) $net)
            ->all();
    }
}
