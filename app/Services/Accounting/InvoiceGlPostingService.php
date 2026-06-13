<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Enums\TaxLineTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Posts a balanced sales journal when an invoice is approved:
 *
 *   DR  Accounts Receivable (customer_account_id)     — grand total
 *   CR  Sales Revenue       (sales_account_id)        — taxable base + exempt + zero-rated amounts
 *   CR  VAT Output          (vat_account_id)          — VAT on taxable lines only
 *
 * Throws ValidationException when required account settings are not configured,
 * so callers (invoice approval) surface a clear error instead of silently
 * leaving an approved invoice without a GL journal.
 * Idempotent: will not post a second journal if one already exists for this invoice.
 */
class InvoiceGlPostingService
{
    public function __construct(
        private JournalBalanceGuard $balanceGuard,
        private PeriodLockGuard $periodGuard,
        private GlAccountConfigGuard $glAccountGuard,
    ) {}

    /**
     * Whether a sales journal already exists for this invoice. Scope-free so the
     * answer never depends on the current branch/company tenant context.
     */
    public function isPosted(Invoice $invoice): bool
    {
        return $this->alreadyPosted($invoice);
    }

    public function postFromInvoice(Invoice $invoice): void
    {
        if ($this->alreadyPosted($invoice)) {
            return;
        }

        $invoice->loadMissing(['invoiceItems', 'discount']);

        $hasTax = $invoice->invoiceItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum('tax_amount') > 0;

        // Throws ValidationException when required accounts are not configured.
        $this->glAccountGuard->assertSalesPostable($hasTax);

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->first();

        $receivableAccountId = $settings->customer_account_id;
        $salesAccountId = $settings->sales_account_id;
        $vatAccountId = $settings->vat_account_id;

        $vatTaxableBase = round((float) $invoice->invoiceItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount), 2);

        $vatAmount = round((float) $invoice->invoiceItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum('tax_amount'), 2);

        $nonVatBase = round((float) $invoice->invoiceItems
            ->whereIn('tax_line_type', [TaxLineTypeEnum::EXEMPT, TaxLineTypeEnum::ZERO_RATED])
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount), 2);

        $orderDiscountAmount = round((float) ($invoice->discount?->amount ?? 0), 2);
        $salesBase = round($vatTaxableBase + $nonVatBase - $orderDiscountAmount, 2);
        $grandTotal = round($salesBase + $vatAmount, 2);

        if ($grandTotal <= 0) {
            return;
        }

        $this->periodGuard->assertPostable($invoice->company_id, $invoice->fiscal_year_id, $invoice->invoice_date);

        $user = \App\Models\User::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->find($invoice->approve_user_id)
            ?? \App\Models\User::withoutGlobalScopes()
                ->where('company_id', $invoice->company_id)
                ->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post sales journal: no user found for this company.',
            ]);
        }

        $company = \App\Models\Company::with('fiscalYear')->find($invoice->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post sales journal: no active fiscal year is set for this company.',
            ]);
        }

        $yearCode = $company->fiscalYear?->year_code ?? '';
        $voucherNo = 'SALE-JV-'.$invoice->id.($yearCode ? '/'.$yearCode : '');

        DB::transaction(function () use (
            $invoice, $grandTotal, $salesBase, $vatAmount,
            $receivableAccountId, $salesAccountId, $vatAccountId,
            $user, $company, $voucherNo
        ) {
            $journal = Journal::withoutGlobalScopes()->create([
                'company_id' => $invoice->company_id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'type' => JournalTypeEnum::INVOICE,
                'reference_type' => $invoice->getMorphClass(),
                'reference_id' => $invoice->id,
                'voucher_no' => $voucherNo,
                'reference_no' => $invoice->invoice_no,
                'date' => $invoice->invoice_date instanceof \Carbon\Carbon
                    ? $invoice->invoice_date->toDateString()
                    : $invoice->invoice_date,
                'remarks' => 'Sales journal for invoice '.$invoice->invoice_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);

            // DR Accounts Receivable — full invoice amount
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $receivableAccountId,
                'dr_amount' => $grandTotal,
                'cr_amount' => 0,
                'remarks' => 'Accounts receivable – '.$invoice->invoice_no,
            ]);

            // CR Sales Revenue — net of VAT
            JournalItem::create([
                'journal_id' => $journal->id,
                'account_id' => $salesAccountId,
                'dr_amount' => 0,
                'cr_amount' => $salesBase,
                'remarks' => 'Sales revenue – '.$invoice->invoice_no,
            ]);

            // CR VAT Output — only when VAT amount > 0 and vat_account is configured.
            // GlAccountConfigGuard ensures vat_account_id is set when hasTax is true,
            // so the elseif branch here is only reached for edge cases (e.g. VAT rounding
            // producing a tiny amount when assertSalesPostable was called with hasTax=false).
            if ($vatAmount > 0 && $vatAccountId) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $vatAccountId,
                    'dr_amount' => 0,
                    'cr_amount' => $vatAmount,
                    'remarks' => 'Output VAT – '.$invoice->invoice_no,
                ]);
            } elseif ($vatAmount > 0) {
                // Fold residual VAT rounding into sales line to keep journal balanced.
                JournalItem::withoutGlobalScopes()
                    ->where('journal_id', $journal->id)
                    ->where('account_id', $salesAccountId)
                    ->increment('cr_amount', $vatAmount);
            }

            $this->balanceGuard->assertBalanced($journal);
        });
    }

    private function alreadyPosted(Invoice $invoice): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->where('reference_type', $invoice->getMorphClass())
            ->where('reference_id', $invoice->id)
            ->where('type', JournalTypeEnum::INVOICE->value)
            ->exists();
    }
}
