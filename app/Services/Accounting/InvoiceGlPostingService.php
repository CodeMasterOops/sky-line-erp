<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Models\Journal;
use App\Models\TaxGroup;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\JournalTypeEnum;
use App\Enums\TaxLineTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\Tax\TaxCalculationEngine;
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
        private TaxCalculationEngine $taxEngine,
        private BooksHealthService $booksHealth,
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
        $roundingAccountId = $settings->rounding_account_id;

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

        // Build per-tax-line VAT entries: group items by tax_group_id, then by individual tax_id.
        $taxGroupLines = $this->buildTaxGroupGlLines($invoice, $vatAccountId);

        DB::transaction(function () use (
            $invoice, $grandTotal, $salesBase, $vatAmount,
            $receivableAccountId, $salesAccountId, $vatAccountId, $roundingAccountId,
            $user, $company, $voucherNo, $taxGroupLines
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

            if (! empty($taxGroupLines)) {
                // Per-tax-line GL posting: each tax posts to its own gl_account_id when set.
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
                // Standard single VAT account posting (legacy / single-tax items).
                JournalItem::create([
                    'journal_id' => $journal->id,
                    'account_id' => $vatAccountId,
                    'dr_amount' => 0,
                    'cr_amount' => $vatAmount,
                    'remarks' => 'Output VAT – '.$invoice->invoice_no,
                ]);
            }

            // Reconcile any residual between the AR debit and the credit legs (most
            // often sub-rupee tax-group rounding) to the dedicated rounding account,
            // keeping revenue and VAT lines clean. Falls back to folding into sales
            // when no rounding account is configured.
            $actualVat = ! empty($taxGroupLines)
                ? round(array_sum(array_column($taxGroupLines, 'amount')), 2)
                : (($vatAmount > 0 && $vatAccountId) ? $vatAmount : 0.0);
            $rounding = round($grandTotal - ($salesBase + $actualVat), 2);

            if (abs($rounding) >= 0.01) {
                if ($roundingAccountId) {
                    JournalItem::create([
                        'journal_id' => $journal->id,
                        'account_id' => $roundingAccountId,
                        'dr_amount' => $rounding < 0 ? abs($rounding) : 0,
                        'cr_amount' => $rounding > 0 ? $rounding : 0,
                        'remarks' => 'Rounding – '.$invoice->invoice_no,
                    ]);
                } else {
                    JournalItem::withoutGlobalScopes()
                        ->where('journal_id', $journal->id)
                        ->where('account_id', $salesAccountId)
                        ->increment('cr_amount', $rounding);
                }
            }

            $this->balanceGuard->assertBalanced($journal);
            $this->booksHealth->invalidateCache($invoice->company_id);
        });
    }

    /**
     * Build per-tax GL lines for invoice items that use tax groups.
     * Returns an empty array when no items use a tax group (fallback to legacy single-account posting).
     *
     * @return array<int, array{account_id: int, amount: float, remarks: string}>
     */
    private function buildTaxGroupGlLines(Invoice $invoice, ?int $fallbackVatAccountId): array
    {
        $groupItems = $invoice->invoiceItems->filter(fn ($item) => $item->tax_group_id !== null);

        if ($groupItems->isEmpty()) {
            return [];
        }

        // Accumulate amounts per GL account across all group-taxed lines.
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
                    'remarks' => 'Output VAT (tax group) – '.$invoice->invoice_no,
                ];
            }
        }

        return $lines;
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
