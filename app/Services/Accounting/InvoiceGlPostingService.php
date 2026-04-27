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

/**
 * Posts a balanced sales journal when an invoice is approved:
 *
 *   DR  Accounts Receivable (customer_account_id)     — grand total
 *   CR  Sales Revenue       (sales_account_id)        — taxable base + exempt + zero-rated amounts
 *   CR  VAT Output          (vat_account_id)          — VAT on taxable lines only
 *
 * Silently skips posting when required account settings are not configured.
 * Idempotent: will not post a second journal if one already exists for this invoice.
 */
class InvoiceGlPostingService
{
    public function postFromInvoice(Invoice $invoice): void
    {
        if ($this->alreadyPosted($invoice)) {
            return;
        }

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->first();

        if (! $settings) {
            return;
        }

        $receivableAccountId = $settings->customer_account_id;
        $salesAccountId = $settings->sales_account_id;
        $vatAccountId = $settings->vat_account_id;

        if (! $receivableAccountId || ! $salesAccountId) {
            return;
        }

        $invoice->loadMissing('invoiceItems');

        $vatTaxableBase = round((float) $invoice->invoiceItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount), 2);

        $vatAmount = round((float) $invoice->invoiceItems
            ->where('tax_line_type', TaxLineTypeEnum::TAXABLE->value)
            ->sum('tax_amount'), 2);

        $nonVatBase = round((float) $invoice->invoiceItems
            ->whereIn('tax_line_type', [TaxLineTypeEnum::EXEMPT->value, TaxLineTypeEnum::ZERO_RATED->value])
            ->sum(fn ($item) => ($item->quantity * $item->rate) - $item->discount_amount), 2);

        $salesBase = round($vatTaxableBase + $nonVatBase, 2);
        $grandTotal = round($salesBase + $vatAmount, 2);

        if ($grandTotal <= 0) {
            return;
        }

        $user = \App\Models\User::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->find($invoice->approve_user_id)
            ?? \App\Models\User::withoutGlobalScopes()
                ->where('company_id', $invoice->company_id)
                ->first();

        if (! $user) {
            return;
        }

        $company = \App\Models\Company::with('fiscalYear')->find($invoice->company_id);
        if (! $company || ! $company->fiscal_year_id) {
            return;
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
                'type' => JournalTypeEnum::SALE,
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

            // CR VAT Output — only when VAT amount > 0 and vat_account is configured
            if ($vatAmount > 0 && $vatAccountId) {
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $vatAccountId,
                    'dr_amount' => 0,
                    'cr_amount' => $vatAmount,
                    'remarks' => 'Output VAT – '.$invoice->invoice_no,
                ]);
            } elseif ($vatAmount > 0) {
                // No vat_account configured: fold VAT into sales line to keep journal balanced
                JournalItem::withoutGlobalScopes()
                    ->where('journal_id', $journal->id)
                    ->where('account_id', $salesAccountId)
                    ->increment('cr_amount', $vatAmount);
            }
        });
    }

    private function alreadyPosted(Invoice $invoice): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->where('reference_type', $invoice->getMorphClass())
            ->where('reference_id', $invoice->id)
            ->where('type', JournalTypeEnum::SALE->value)
            ->exists();
    }
}
