<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Models\Journal;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use App\Services\Tax\TaxCalculationEngine;

readonly class ExpenseService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private GlAccountConfigGuard $glAccountGuard,
        private JournalBalanceGuard $balanceGuard,
        private PeriodLockGuard $periodGuard,
        private JournalVoidService $journalVoid,
        private TaxCalculationEngine $taxEngine,
    ) {}

    /**
     * Void an approved expense: reverse its GL journal (soft-delete, audit-safe)
     * and stamp voided_at. Mirrors invoice/bill void; journal removal is
     * idempotent so a re-void or an expense that never posted is a safe no-op.
     */
    public function voidExpense(Expense $expense): void
    {
        DB::transaction(function () use ($expense) {
            $this->journalVoid->reverseForReference($expense);
            $expense->update(['voided_at' => now()]);
        });
    }

    /**
     * Whether an expense journal already exists. Scope-free and excludes
     * soft-deleted journals so it matches the unposted-documents report.
     */
    public function isPosted(Expense $expense): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $expense->company_id)
            ->where('reference_type', $expense->getMorphClass())
            ->where('reference_id', $expense->id)
            ->where('type', JournalTypeEnum::EXPENSE->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Idempotently post an expense journal for an approved expense that is
     * missing one. Guards the payable/VAT accounts; no-ops if already posted.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function repost(Expense $expense): void
    {
        if ($this->isPosted($expense)) {
            return;
        }

        DB::transaction(fn () => $this->createJournal($expense));
    }

    public function createExpense(array $formData): Expense
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $expenseNo = ! empty($formData['expense_no'])
                ? $formData['expense_no']
                : $this->documentNumberGenerator->fiscalYear(
                    Expense::class,
                    'EX-',
                    $fiscalYearId,
                    $setting->fiscalYear?->year_code,
                );
            $expense = Expense::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'expense_no' => $expenseNo,
                'date' => $formData['date'],
                'due_date' => $formData['due_date'] ?? null,
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = array_map(fn ($item) => $this->resolveItemTax($item), $formData['items'] ?? []);
            $expense->expenseItems()->createMany($this->mapItems($items));

            if ($status === StatusEnum::APPROVED->value) {
                $this->createJournal($expense);
            }

            return $expense;
        });
    }

    public function updateExpense(array $formData, Expense $expense): void
    {
        DB::transaction(function () use ($expense, $formData) {
            $expense->update([
                'party_id' => $formData['party_id'] ?? null,
                'date' => $formData['date'],
                'due_date' => $formData['due_date'] ?? null,
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $expense->expenseItems()->delete();
            $items = array_map(fn ($item) => $this->resolveItemTax($item), $formData['items'] ?? []);
            $expense->expenseItems()->createMany($this->mapItems($items));
        });
    }

    public function approveExpense(Expense $expense): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($expense, $user) {
            $expense->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($expense);
        });
    }

    private function createJournal(Expense $expense): void
    {
        $expense->loadMissing('expenseItems.account', 'expenseItems.taxGroup.taxGroupMembers.tax', 'party:id,name');

        // Single chokepoint guard: protects approve-on-create, approveExpense and repost.
        $hasTax = (float) $expense->expenseItems->sum('tax_amount') > 0;
        $this->glAccountGuard->assertExpensePostable($hasTax);
        $this->periodGuard->assertPostable($expense->company_id, $expense->fiscal_year_id, $expense->date);

        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $expense->company_id)
            ->first();

        $journal = $expense->journal()->create([
            'company_id' => $expense->company_id,
            'fiscal_year_id' => $expense->fiscal_year_id,
            'type' => JournalTypeEnum::EXPENSE->value,
            'voucher_no' => $expense->expense_no,
            'reference_no' => $expense->reference_no,
            'date' => $expense->date,
            'remarks' => $expense->remarks,
            'create_user_id' => $expense->create_user_id,
            'approve_user_id' => $expense->approve_user_id,
            'approved_at' => $expense->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        $expenseLines = $this->groupExpenseLines($expense->expenseItems);
        $taxTotal = 0.0;
        $grandTotal = 0.0;

        foreach ($expense->expenseItems as $item) {
            $netAmount = round((float) $item->amount - (float) $item->discount_amount, 2);
            $taxAmount = round((float) $item->tax_amount, 2);

            $taxTotal += $taxAmount;
            $grandTotal += $netAmount + $taxAmount;
        }

        foreach ($expenseLines as $line) {
            if ($line['amount'] <= 0) {
                continue;
            }

            $journal->journalItems()->create([
                'account_id' => $line['account_id'],
                'dr_amount' => $line['amount'],
                'cr_amount' => 0,
                'remarks' => 'To-'.($line['account_name'] ?? ''),
            ]);
        }

        $taxGroupLines = $this->buildTaxGroupGlLines($expense, $accountSetting->vat_account_id);
        if (! empty($taxGroupLines)) {
            foreach ($taxGroupLines as $line) {
                $journal->journalItems()->create([
                    'account_id' => $line['account_id'],
                    'dr_amount' => $line['amount'],
                    'cr_amount' => 0,
                    'remarks' => $line['remarks'],
                ]);
            }
        } elseif ($taxTotal > 0) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->vat_account_id,
                'dr_amount' => round($taxTotal, 2),
                'cr_amount' => 0,
                'remarks' => 'To-'.($expense->party->name ?? ''),
            ]);
        }

        $journal->journalItems()->create([
            'account_id' => $accountSetting->supplier_account_id,
            'dr_amount' => 0,
            'cr_amount' => round($grandTotal, 2),
            'remarks' => 'To-Expense Account',
        ]);

        $this->balanceGuard->assertBalanced($journal);
    }

    private function groupExpenseLines(Collection $items): Collection
    {
        return $items
            ->groupBy('account_id')
            ->map(function (Collection $group, int|string $accountId) {
                $first = $group->first();

                return [
                    'account_id' => (int) $accountId,
                    'account_name' => $first?->account?->name,
                    'amount' => round($group->sum(fn ($item) => (float) $item->amount - (float) $item->discount_amount), 2),
                ];
            })
            ->values();
    }

    private function mapItems(array $items): array
    {
        return collect($items)->map(function ($item) {
            return [
                'account_id' => $item['account_id'],
                'amount' => $item['amount'],
                'tax_id' => $item['tax_id'] ?? null,
                'tax_group_id' => $item['tax_group_id'] ?? null,
                'tax_amount' => $item['tax_amount'] ?? 0,
                'discount_amount' => $item['discount_amount'] ?? 0,
            ];
        })->all();
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private function resolveItemTax(array $item): array
    {
        $taxGroupId = $item['tax_group_id'] ?? null;
        if (! $taxGroupId) {
            return $item;
        }

        $group = TaxGroup::withoutGlobalScopes()->find($taxGroupId);
        if (! $group) {
            return $item;
        }

        $baseAmount = round((float) $item['amount'] - (float) ($item['discount_amount'] ?? 0), 2);
        $result = $this->taxEngine->calculateForGroup($baseAmount, $group);
        $item['tax_amount'] = $result->totalTaxAmount;

        return $item;
    }

    /**
     * @return array<int, array{account_id: int, amount: float, remarks: string}>
     */
    private function buildTaxGroupGlLines(Expense $expense, ?int $fallbackVatAccountId): array
    {
        $totals = [];

        foreach ($expense->expenseItems as $item) {
            if (! $item->taxGroup) {
                continue;
            }

            $baseAmount = round((float) $item->amount - (float) $item->discount_amount, 2);
            $result = $this->taxEngine->calculateForGroup($baseAmount, $item->taxGroup);

            foreach ($result->lines as $line) {
                $glAccountId = $line['gl_account_id'] ?? $fallbackVatAccountId;
                if (! $glAccountId) {
                    continue;
                }

                $totals[$glAccountId] = ($totals[$glAccountId] ?? 0.0) + $line['amount'];
            }
        }

        if (empty($totals)) {
            return [];
        }

        $remarks = 'Input VAT (tax group) – '.$expense->expense_no;
        $lines = [];
        foreach ($totals as $accountId => $amount) {
            if (round($amount, 2) <= 0) {
                continue;
            }

            $lines[] = [
                'account_id' => $accountId,
                'amount' => round($amount, 2),
                'remarks' => $remarks,
            ];
        }

        return $lines;
    }
}
