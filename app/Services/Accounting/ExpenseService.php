<?php

namespace App\Services\Accounting;

use App\Models\Expense;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

readonly class ExpenseService
{
    public function createExpense(array $formData): Expense
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $expenseNo = ! empty($formData['expense_no'])
            ? $formData['expense_no']
            : $this->generateExpenseNo($fiscalYearId, $setting->fiscalYear?->year_code);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $expenseNo) {
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

            $expense->expenseItems()->createMany($this->mapItems($formData['items'] ?? []));

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
            $expense->expenseItems()->createMany($this->mapItems($formData['items'] ?? []));
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
        $expense->loadMissing('expenseItems.account', 'party:id,name');

        $accountSetting = AccountSetting::first();

        $journal = $expense->journal()->create([
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

        if ($taxTotal > 0) {
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
                'tax_amount' => $item['tax_amount'] ?? 0,
                'discount_amount' => $item['discount_amount'] ?? 0,
            ];
        })->all();
    }

    private function generateExpenseNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Expense::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'EX-'.($count + 1).$suffix;
    }
}
