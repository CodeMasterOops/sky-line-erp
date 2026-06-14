<?php

namespace App\Services\Accounting;

use App\Models\User;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Enums\TaxLineTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;

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
            return;
        }

        $company = Company::with('fiscalYear')->find($creditNote->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            return;
        }

        $yearCode = $company->fiscalYear?->year_code ?? '';
        $voucherNo = 'SRET-JV-'.$creditNote->id.($yearCode ? '/'.$yearCode : '');

        DB::transaction(function () use (
            $creditNote, $grandTotal, $salesBase, $vatAmount,
            $receivableAccountId, $salesAccountId, $vatAccountId,
            $user, $company, $voucherNo
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

            // DR VAT Output — reverse the output VAT charged on the sale
            if ($vatAmount > 0 && $vatAccountId) {
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
        });
    }
}
