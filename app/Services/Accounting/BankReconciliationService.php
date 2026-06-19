<?php

namespace App\Services\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\BankAccount;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Carbon;
use App\Models\BankMatchingRule;
use App\Models\BankStatementLine;
use App\Models\BankReconciliation;
use Illuminate\Support\Facades\DB;
use App\Models\ReconciliationAuditLog;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;

/**
 * The home for all bank-reconciliation business logic (formerly scattered across
 * BankReconciliationController). Owns GL balance computation, the matching engine
 * (rules → set-based auto-match → manual → create-on-match → suspense parking),
 * reconciliation sessions with lock + audit trail.
 *
 * Sign convention (asset/debit-normal bank ledger, matching how receipts/payments
 * actually post): statement CREDIT = money in = Dr bank ledger; statement DEBIT =
 * money out = Cr bank ledger. Book balance = Σ(dr - cr) over the bank's GL account.
 */
class BankReconciliationService
{
    private const MATCH_AMOUNT_TOLERANCE = 0.01;

    private const MATCH_DATE_WINDOW_DAYS = 3;

    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
        private readonly JournalBalanceGuard $balanceGuard,
        private readonly PeriodLockGuard $periodGuard,
    ) {}

    /**
     * Book balance of the bank's GL account (debit-normal) up to and including $asOf.
     */
    public function glBalance(BankAccount $bankAccount, ?string $asOf = null): float
    {
        $balance = JournalItem::query()
            ->whereHas('journal', function ($q) use ($bankAccount, $asOf) {
                $q->where('company_id', $bankAccount->company_id)
                    ->where('status', StatusEnum::APPROVED->value);
                if ($asOf) {
                    $q->whereDate('date', '<=', $asOf);
                }
            })
            ->where('account_id', $bankAccount->account_id)
            ->selectRaw('COALESCE(SUM(dr_amount - cr_amount), 0) as balance')
            ->value('balance');

        return round((float) $bankAccount->opening_balance + (float) $balance, 2);
    }

    /**
     * Statement balance = opening balance + net of imported lines up to $asOf.
     */
    public function statementBalance(BankAccount $bankAccount, ?string $asOf = null): float
    {
        $query = BankStatementLine::where('bank_account_id', $bankAccount->id);
        if ($asOf) {
            $query->whereDate('transaction_date', '<=', $asOf);
        }

        return round((float) $bankAccount->opening_balance + (float) $query->sum('credit') - (float) $query->sum('debit'), 2);
    }

    /**
     * Set-based auto-match (replaces the N+1, sign-inverted controller version):
     * loads all unmatched lines and all unreconciled bank journal items once, then
     * matches in PHP. Confirms only unambiguous 1:1 candidates (single item within
     * amount tolerance and the date window).
     */
    public function autoMatch(BankAccount $bankAccount): int
    {
        $lines = BankStatementLine::where('bank_account_id', $bankAccount->id)
            ->where('status', 'unmatched')
            ->orderBy('transaction_date')
            ->get();

        if ($lines->isEmpty()) {
            return 0;
        }

        $candidates = $this->unmatchedBankJournalItems($bankAccount);
        $consumed = [];
        $matched = 0;

        foreach ($lines as $line) {
            $signed = $line->signedAmount();
            $lineDate = Carbon::parse($line->transaction_date);

            $hits = $candidates->filter(function (JournalItem $item) use ($signed, $lineDate, $consumed) {
                if (isset($consumed[$item->id])) {
                    return false;
                }
                $itemSigned = round((float) $item->dr_amount - (float) $item->cr_amount, 2);
                if (abs($itemSigned - $signed) > self::MATCH_AMOUNT_TOLERANCE) {
                    return false;
                }

                return abs($lineDate->diffInDays(Carbon::parse($item->journal->date))) <= self::MATCH_DATE_WINDOW_DAYS;
            });

            if ($hits->count() === 1) {
                $item = $hits->first();
                $this->bindLineToItem($line, $item->id, 'auto');
                $consumed[$item->id] = true;
                $matched++;
            }
        }

        return $matched;
    }

    /**
     * Rule-based auto-post: for each unmatched line, fire the first active rule
     * (by priority) whose pattern matches, posting a balanced bank↔target journal
     * and marking the line as a rule-created match. Returns the number posted.
     */
    public function applyRules(BankAccount $bankAccount): int
    {
        $rules = BankMatchingRule::where('company_id', $bankAccount->company_id)
            ->where('is_active', true)
            ->where(function ($q) use ($bankAccount) {
                $q->whereNull('bank_account_id')->orWhere('bank_account_id', $bankAccount->id);
            })
            ->orderBy('priority')
            ->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $lines = BankStatementLine::where('bank_account_id', $bankAccount->id)
            ->where('status', 'unmatched')
            ->get();

        $posted = 0;

        foreach ($lines as $line) {
            $rule = $rules->first(fn (BankMatchingRule $r) => $r->matches($line));
            if ($rule === null) {
                continue;
            }

            $this->createEntryForLine($line, $rule->target_account_id, 'rule', $rule->set_status);
            $posted++;
        }

        return $posted;
    }

    /**
     * Create-on-match: post a balanced journal for a genuinely bank-only line
     * (charges, interest, transfer fees) against $contraAccountId, then bind the
     * line to the bank leg. Statement credit ⇒ Dr bank / Cr contra; debit ⇒ reverse.
     */
    public function createEntryForLine(
        BankStatementLine $line,
        int $contraAccountId,
        string $matchType = 'created',
        string $status = 'matched',
    ): BankStatementLine {
        $bankAccount = $this->bankAccountFor($line);

        return DB::transaction(function () use ($line, $bankAccount, $contraAccountId, $matchType, $status) {
            $bankItem = $this->postBankJournal(
                $bankAccount,
                $line->signedAmount(),
                $contraAccountId,
                Carbon::parse($line->transaction_date)->toDateString(),
                $line->description ?: 'Bank reconciliation entry',
            );

            $before = $line->only(['status', 'journal_item_id', 'match_type']);
            $line->update([
                'journal_item_id' => $bankItem->id,
                'status' => $status,
                'match_type' => $matchType,
            ]);
            $this->audit($line, 'created', $before, $line->only(['status', 'journal_item_id', 'match_type']));

            return $line->fresh();
        });
    }

    /**
     * Park an unexplained line to the company Suspense account, to be cleared later.
     */
    public function parkToSuspense(BankStatementLine $line): BankStatementLine
    {
        $bankAccount = $this->bankAccountFor($line);
        $suspenseId = $this->companySetting($bankAccount->company_id)?->suspense_account_id;

        if (! $suspenseId) {
            throw ValidationException::withMessages([
                'suspense' => 'No suspense account is configured for this company.',
            ]);
        }

        return $this->createEntryForLine($line, (int) $suspenseId, 'created', 'matched');
    }

    /**
     * Manually bind a statement line to an existing journal item, with company
     * scoping enforced by the caller. Writes an audit row.
     */
    public function manualMatch(BankStatementLine $line, int $journalItemId): BankStatementLine
    {
        return DB::transaction(function () use ($line, $journalItemId) {
            $this->bindLineToItem($line, $journalItemId, 'manual');

            return $line->fresh();
        });
    }

    public function unmatch(BankStatementLine $line): BankStatementLine
    {
        if ($line->reconciliation_id && optional($line->reconciliation)->isLocked()) {
            throw ValidationException::withMessages([
                'line' => 'This line belongs to a locked reconciliation and cannot be unmatched.',
            ]);
        }

        return DB::transaction(function () use ($line) {
            $before = $line->only(['status', 'journal_item_id', 'match_type']);
            $line->update(['journal_item_id' => null, 'status' => 'unmatched', 'match_type' => null]);
            $this->audit($line, 'unmatched', $before, $line->only(['status', 'journal_item_id', 'match_type']));

            return $line->fresh();
        });
    }

    /**
     * Open a draft reconciliation for a period, snapshotting the GL balance.
     */
    public function startReconciliation(BankAccount $bankAccount, array $data): BankReconciliation
    {
        $glBalance = $this->glBalance($bankAccount, $data['period_end']);

        return BankReconciliation::create([
            'company_id' => $bankAccount->company_id,
            'branch_id' => $bankAccount->branch_id,
            'bank_account_id' => $bankAccount->id,
            'period_start' => $data['period_start'],
            'period_end' => $data['period_end'],
            'statement_opening_balance' => $data['statement_opening_balance'] ?? $bankAccount->opening_balance,
            'statement_closing_balance' => $data['statement_closing_balance'] ?? 0,
            'gl_balance' => $glBalance,
            'difference' => round(($data['statement_closing_balance'] ?? 0) - $glBalance, 2),
            'status' => 'draft',
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Complete and lock a reconciliation: requires zero difference, stamps the
     * reconciler, links the period's matched lines, and writes back to the account.
     */
    public function completeReconciliation(BankReconciliation $reconciliation): BankReconciliation
    {
        $bankAccount = $reconciliation->bankAccount;
        $glBalance = $this->glBalance($bankAccount, $reconciliation->period_end->toDateString());
        $difference = round((float) $reconciliation->statement_closing_balance - $glBalance, 2);

        if (abs($difference) > 0.01) {
            throw ValidationException::withMessages([
                'difference' => 'Cannot complete: statement and book balances differ by '.number_format($difference, 2).'. Match or park the remaining lines first.',
            ]);
        }

        return DB::transaction(function () use ($reconciliation, $bankAccount, $glBalance) {
            BankStatementLine::where('bank_account_id', $bankAccount->id)
                ->whereBetween('transaction_date', [$reconciliation->period_start, $reconciliation->period_end])
                ->where('status', 'matched')
                ->whereNull('reconciliation_id')
                ->update(['reconciliation_id' => $reconciliation->id]);

            $reconciliation->update([
                'gl_balance' => $glBalance,
                'difference' => 0,
                'status' => 'locked',
                'reconciled_by' => auth('admin')->id(),
                'reconciled_at' => now(),
            ]);

            $bankAccount->update([
                'last_reconciled_at' => now(),
                'last_reconciled_balance' => $reconciliation->statement_closing_balance,
            ]);

            $this->audit($reconciliation, 'locked', null, ['gl_balance' => $glBalance], $bankAccount->id, $reconciliation->id);

            return $reconciliation->fresh();
        });
    }

    public function reopenReconciliation(BankReconciliation $reconciliation): BankReconciliation
    {
        return DB::transaction(function () use ($reconciliation) {
            $before = $reconciliation->only(['status']);
            $reconciliation->statementLines()->update(['reconciliation_id' => null]);
            $reconciliation->update(['status' => 'draft', 'reconciled_by' => null, 'reconciled_at' => null]);
            $this->audit($reconciliation, 'reopened', $before, ['status' => 'draft'], $reconciliation->bank_account_id, $reconciliation->id);

            return $reconciliation->fresh();
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, JournalItem>
     */
    private function unmatchedBankJournalItems(BankAccount $bankAccount): \Illuminate\Support\Collection
    {
        return JournalItem::query()
            ->with('journal:id,date')
            ->whereHas('journal', function ($q) use ($bankAccount) {
                $q->where('company_id', $bankAccount->company_id)
                    ->where('status', StatusEnum::APPROVED->value);
            })
            ->where('account_id', $bankAccount->account_id)
            ->whereNotExists(function ($q) {
                $q->from('bank_statement_lines')
                    ->whereColumn('bank_statement_lines.journal_item_id', 'journal_items.id');
            })
            ->get();
    }

    private function bindLineToItem(BankStatementLine $line, int $journalItemId, string $matchType): void
    {
        $before = $line->only(['status', 'journal_item_id', 'match_type']);
        $line->update([
            'journal_item_id' => $journalItemId,
            'status' => 'matched',
            'match_type' => $matchType,
        ]);
        $this->audit($line, 'matched', $before, $line->only(['status', 'journal_item_id', 'match_type']));
    }

    /**
     * Post a balanced two-line journal between the bank ledger and a contra account.
     * Returns the bank-leg journal item (the one a statement line binds to).
     */
    private function postBankJournal(
        BankAccount $bankAccount,
        float $signedAmount,
        int $contraAccountId,
        string $date,
        string $remarks,
    ): JournalItem {
        $this->periodGuard->assertPostable($bankAccount->company_id, $bankAccount->company->fiscal_year_id, $date);

        $fiscalYearId = $bankAccount->company->fiscal_year_id;
        $userId = auth('admin')->id();

        $voucherNo = $this->documentNumberGenerator->journalVoucher(
            JournalTypeEnum::JOURNAL_VOUCHER,
            'BRJ-',
            $fiscalYearId,
            $bankAccount->company->fiscalYear?->year_code,
        );

        $journal = Journal::create([
            'company_id' => $bankAccount->company_id,
            'branch_id' => $bankAccount->branch_id,
            'fiscal_year_id' => $fiscalYearId,
            'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
            'voucher_no' => $voucherNo,
            'date' => $date,
            'remarks' => $remarks,
            'create_user_id' => $userId,
            'approve_user_id' => $userId,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $amount = round(abs($signedAmount), 2);
        $moneyIn = $signedAmount >= 0;

        $bankItem = $journal->journalItems()->create([
            'account_id' => $bankAccount->account_id,
            'dr_amount' => $moneyIn ? $amount : 0,
            'cr_amount' => $moneyIn ? 0 : $amount,
            'remarks' => $remarks,
        ]);

        $journal->journalItems()->create([
            'account_id' => $contraAccountId,
            'dr_amount' => $moneyIn ? 0 : $amount,
            'cr_amount' => $moneyIn ? $amount : 0,
            'remarks' => $remarks,
        ]);

        $this->balanceGuard->assertBalanced($journal);

        return $bankItem;
    }

    private function bankAccountFor(BankStatementLine $line): BankAccount
    {
        return $line->bankAccount()->withoutGlobalScopes()->firstOrFail();
    }

    private function companySetting(int $companyId): ?AccountSetting
    {
        return AccountSetting::withoutGlobalScopes()->where('company_id', $companyId)->first();
    }

    private function audit(
        \Illuminate\Database\Eloquent\Model $auditable,
        string $action,
        ?array $before,
        ?array $after,
        ?int $bankAccountId = null,
        ?int $reconciliationId = null,
    ): void {
        $companyId = $auditable->company_id
            ?? ($auditable instanceof BankStatementLine ? $this->bankAccountFor($auditable)->company_id : null);

        if ($auditable instanceof BankStatementLine) {
            $bankAccountId ??= $auditable->bank_account_id;
            $reconciliationId ??= $auditable->reconciliation_id;
        }

        ReconciliationAuditLog::create([
            'company_id' => $companyId,
            'bank_account_id' => $bankAccountId,
            'bank_reconciliation_id' => $reconciliationId,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'user_id' => auth('admin')->id(),
        ]);
    }
}
