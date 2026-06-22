<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\Company;
use App\Models\Journal;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Enums\TaxLineTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\Tax\TaxCalculationEngine;

/**
 * Posts a balanced sales-return journal when a credit note is approved,
 * reversing the original sale:
 *
 *   DR  Sales Revenue       (sales_account_id)     — taxable base
 *   DR  VAT Output          (vat_account_id)       — VAT reversed
 *   CR  Accounts Receivable (customer_account_id)  — grand total
 *
 * Idempotent: never posts a second journal for the same credit note. Guards on
 * the sales control accounts so a return can't post into a broken ledger.
 */
class CreditNoteGlPostingService
{
    public function __construct(
        private GlAccountConfigGuard $glAccountGuard,
        private JournalBalanceGuard $balanceGuard,
        private PeriodLockGuard $periodGuard,
        private BooksHealthService $booksHealth,
        private TaxCalculationEngine $taxEngine,
    ) {}

    public function isPosted(CreditNote $creditNote): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $creditNote->company_id)
            ->where('reference_type', $creditNote->getMorphClass())
            ->where('reference_id', $creditNote->id)
            ->where('type', JournalTypeEnum::CREDIT_NOTE->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    public function postFromCreditNote(CreditNote $creditNote): void
    {
        if ($this->isPosted($creditNote)) {
            return;
        }

        $creditNote->loadMissing('creditNoteItems', 'discount');

        $vatTaxableBase = round((float) $creditNote->creditNoteItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum(fn ($item) => ((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount), 2);

        $nonVatBase = round((float) $creditNote->creditNoteItems
            ->whereIn('tax_line_type', [TaxLineTypeEnum::EXEMPT, TaxLineTypeEnum::ZERO_RATED])
            ->sum(fn ($item) => ((float) $item->quantity * (float) $item->rate) - (float) $item->discount_amount), 2);

        $vatAmount = round((float) $creditNote->creditNoteItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum('tax_amount'), 2);

        $orderDiscountAmount = round((float) ($creditNote->discount?->amount ?? 0), 2);
        $salesBase = round($vatTaxableBase + $nonVatBase - $orderDiscountAmount, 2);
        $grandTotal = round($salesBase + $vatAmount, 2);

        if ($grandTotal <= 0) {
            return;
        }

        $this->glAccountGuard->assertSalesPostable($vatAmount > 0);
        $this->periodGuard->assertPostable($creditNote->company_id, $creditNote->fiscal_year_id, $creditNote->credit_note_date);

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $creditNote->company_id)
            ->first();

        $receivableAccountId = $settings->customer_account_id;
        $salesAccountId = $settings->sales_account_id;
        $vatAccountId = $settings->vat_account_id;

        $user = User::withoutGlobalScopes()
            ->where('company_id', $creditNote->company_id)
            ->find($creditNote->approve_user_id)
            ?? User::withoutGlobalScopes()
                ->where('company_id', $creditNote->company_id)
                ->first();

        if (! $user) {
            throw new \RuntimeException("Cannot post GL for credit note {$creditNote->id}: no user found for company {$creditNote->company_id}.");
        }

        $company = Company::with('fiscalYear')->find($creditNote->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            throw new \RuntimeException("Cannot post GL for credit note {$creditNote->id}: company or fiscal year not configured.");
        }

        $yearCode = $company->fiscalYear?->year_code ?? '';
        $voucherNo = 'SRET-JV-'.$creditNote->id.($yearCode ? '/'.$yearCode : '');

        $taxGroupLines = $this->buildTaxGroupGlLines($creditNote, $vatAccountId);

        DB::transaction(function () use (
            $creditNote, $grandTotal, $salesBase, $vatAmount,
            $receivableAccountId, $salesAccountId, $vatAccountId,
            $user, $company, $voucherNo, $taxGroupLines
        ) {
            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $creditNote->company_id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'type' => JournalTypeEnum::CREDIT_NOTE,
                'reference_type' => $creditNote->getMorphClass(),
                'reference_id' => $creditNote->id,
                'voucher_no' => $voucherNo,
                'reference_no' => $creditNote->credit_note_no,
                'date' => $creditNote->credit_note_date,
                'remarks' => 'Sales return journal for credit note '.$creditNote->credit_note_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            // DR Sales Revenue — reverse the recognised sale (net of VAT)
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $salesAccountId,
                'dr_amount' => $salesBase,
                'cr_amount' => 0,
                'remarks' => 'Sales return – '.$creditNote->credit_note_no,
            ]);

            // DR VAT Output — reverse per-tax-group GL accounts (mirrors original invoice posting)
            if (! empty($taxGroupLines)) {
                foreach ($taxGroupLines as $line) {
                    JournalItem::create([
                        'journal_id' => $journal->id,
                        'account_id' => $line['account_id'],
                        'dr_amount' => $line['amount'],
                        'cr_amount' => 0,
                        'remarks' => $line['remarks'],
                    ]);
                }
            } elseif ($vatAmount > 0 && $vatAccountId) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $vatAccountId,
                    'dr_amount' => $vatAmount,
                    'cr_amount' => 0,
                    'remarks' => 'VAT reversed – '.$creditNote->credit_note_no,
                ]);
            } elseif ($vatAmount > 0) {
                JournalItem::withoutGlobalScopes()
                    ->where('journal_id', $journal->id)
                    ->where('account_id', $salesAccountId)
                    ->increment('dr_amount', $vatAmount);
            }

            // CR Accounts Receivable — reduce what the customer owes
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $receivableAccountId,
                'dr_amount' => 0,
                'cr_amount' => $grandTotal,
                'remarks' => 'Accounts receivable – '.$creditNote->credit_note_no,
            ]);

            $this->balanceGuard->assertBalanced($journal);
            $this->booksHealth->invalidateCache($creditNote->company_id);
        });
    }

    /**
     * @return array<int, array{account_id: int, amount: float, remarks: string}>
     */
    private function buildTaxGroupGlLines(CreditNote $creditNote, ?int $fallbackVatAccountId): array
    {
        $groupItems = $creditNote->creditNoteItems->filter(fn ($item) => $item->tax_group_id !== null);

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
                    'remarks' => 'Output VAT reversed (tax group) – '.$creditNote->credit_note_no,
                ];
            }
        }

        return $lines;
    }
}
