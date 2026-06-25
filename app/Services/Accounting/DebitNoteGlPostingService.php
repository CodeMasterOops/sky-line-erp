<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\Company;
use App\Models\Journal;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\DebitNote;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\Tax\TaxCalculationEngine;

/**
 * Posts a balanced purchase-return journal when a debit note is approved,
 * reversing the original purchase:
 *
 *   DR  Accounts Payable (supplier_account_id)  — grand total
 *   CR  Purchases        (purchase_account_id)  — taxable base
 *   CR  VAT Input        (vat_account_id)        — VAT reversed
 *
 * Idempotent: never posts a second journal for the same debit note. Guards on
 * the purchase control accounts so a return can't post into a broken ledger.
 */
class DebitNoteGlPostingService
{
    public function __construct(
        private GlAccountConfigGuard $glAccountGuard,
        private JournalBalanceGuard $balanceGuard,
        private PeriodLockGuard $periodGuard,
        private BooksHealthService $booksHealth,
        private TaxCalculationEngine $taxEngine,
    ) {}

    public function isPosted(DebitNote $debitNote): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $debitNote->company_id)
            ->where('reference_type', $debitNote->getMorphClass())
            ->where('reference_id', $debitNote->id)
            ->where('type', JournalTypeEnum::DEBIT_NOTE->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function postFromDebitNote(DebitNote $debitNote): void
    {
        if ($this->isPosted($debitNote)) {
            return;
        }

        $debitNote->loadMissing('debitNoteItems', 'discount');

        $lineTotal = (float) $debitNote->debitNoteItems
            ->sum(fn ($item) => ((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount);
        $orderDiscountAmount = (float) ($debitNote->discount?->amount ?? 0);
        $purchaseBase = round($lineTotal - $orderDiscountAmount, 2);
        $vatAmount = round((float) $debitNote->debitNoteItems->sum('tax_amount'), 2);
        $grandTotal = round($purchaseBase + $vatAmount, 2);

        if ($grandTotal <= 0) {
            return;
        }

        $this->glAccountGuard->assertPurchasePostable($vatAmount > 0);
        $this->periodGuard->assertPostable($debitNote->company_id, $debitNote->fiscal_year_id, $debitNote->debit_note_date);

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $debitNote->company_id)
            ->first();

        $payableAccountId = $settings->supplier_account_id;
        $purchaseAccountId = $settings->purchase_account_id;
        $vatAccountId = $settings->vat_account_id;

        $user = User::withoutGlobalScopes()
            ->where('company_id', $debitNote->company_id)
            ->find($debitNote->approve_user_id)
            ?? User::withoutGlobalScopes()
                ->where('company_id', $debitNote->company_id)
                ->first();

        if (! $user) {
            throw new \RuntimeException("Cannot post GL for debit note {$debitNote->id}: no user found for company {$debitNote->company_id}.");
        }

        $company = Company::with('fiscalYear')->find($debitNote->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            throw new \RuntimeException("Cannot post GL for debit note {$debitNote->id}: company or fiscal year not configured.");
        }

        $yearCode = $company->fiscalYear?->year_code ?? '';
        $voucherNo = 'PRET-JV-'.$debitNote->id.($yearCode ? '/'.$yearCode : '');

        $taxGroupLines = $this->buildTaxGroupGlLines($debitNote, $vatAccountId);

        DB::transaction(function () use (
            $debitNote, $grandTotal, $purchaseBase, $vatAmount,
            $payableAccountId, $purchaseAccountId, $vatAccountId,
            $user, $company, $voucherNo, $taxGroupLines
        ) {
            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $debitNote->company_id,
                'branch_id' => $debitNote->branch_id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'type' => JournalTypeEnum::DEBIT_NOTE,
                'reference_type' => $debitNote->getMorphClass(),
                'reference_id' => $debitNote->id,
                'voucher_no' => $voucherNo,
                'reference_no' => $debitNote->debit_note_no,
                'date' => $debitNote->debit_note_date,
                'remarks' => 'Purchase return journal for debit note '.$debitNote->debit_note_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            // DR Accounts Payable — reduce what we owe the supplier
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $payableAccountId,
                'dr_amount' => $grandTotal,
                'cr_amount' => 0,
                'remarks' => 'Accounts payable – '.$debitNote->debit_note_no,
            ]);

            // CR Purchases — reverse the recorded purchase (net of VAT)
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $purchaseAccountId,
                'dr_amount' => 0,
                'cr_amount' => $purchaseBase,
                'remarks' => 'Purchase return – '.$debitNote->debit_note_no,
            ]);

            // CR VAT Input — reverse the reclaimable input VAT (per-tax-group or single account)
            if (! empty($taxGroupLines)) {
                foreach ($taxGroupLines as $line) {
                    JournalItem::create([
                        'journal_id' => $journal->id,
                        'account_id' => $line['account_id'],
                        'dr_amount' => 0,
                        'cr_amount' => $line['amount'],
                        'remarks' => $line['remarks'],
                    ]);
                }
            } elseif ($vatAmount > 0 && $vatAccountId) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $vatAccountId,
                    'dr_amount' => 0,
                    'cr_amount' => $vatAmount,
                    'remarks' => 'VAT reversed – '.$debitNote->debit_note_no,
                ]);
            } elseif ($vatAmount > 0) {
                JournalItem::withoutGlobalScopes()
                    ->where('journal_id', $journal->id)
                    ->where('account_id', $purchaseAccountId)
                    ->increment('cr_amount', $vatAmount);
            }

            $this->balanceGuard->assertBalanced($journal);
            $this->booksHealth->invalidateCache($debitNote->company_id);
        });
    }

    /**
     * @return array<int, array{account_id: int, amount: float, remarks: string}>
     */
    private function buildTaxGroupGlLines(DebitNote $debitNote, ?int $fallbackVatAccountId): array
    {
        $groupItems = $debitNote->debitNoteItems->filter(fn ($item) => $item->tax_group_id !== null);

        if ($groupItems->isEmpty()) {
            return [];
        }

        $accountTotals = [];

        foreach ($groupItems as $item) {
            $group = TaxGroup::withoutGlobalScopes()->find($item->tax_group_id);
            if (! $group) {
                continue;
            }

            $baseAmount = round(
                ((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount,
                2,
            );

            $result = $this->taxEngine->calculateForGroup($baseAmount, $group);

            foreach ($result->lines as $taxLine) {
                $accountId = $taxLine['gl_account_id'] ?? $fallbackVatAccountId;
                if (! $accountId) {
                    continue;
                }
                $accountTotals[$accountId] = round(
                    ($accountTotals[$accountId] ?? 0.0) + $taxLine['amount'],
                    2,
                );
            }
        }

        $lines = [];
        foreach ($accountTotals as $accountId => $amount) {
            if ($amount > 0) {
                $lines[] = [
                    'account_id' => $accountId,
                    'amount' => $amount,
                    'remarks' => 'VAT reversed (tax group) – '.$debitNote->debit_note_no,
                ];
            }
        }

        return $lines;
    }
}
