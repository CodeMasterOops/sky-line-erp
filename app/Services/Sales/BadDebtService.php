<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalBalanceGuard;

readonly class BadDebtService
{
    public function __construct(
        private JournalBalanceGuard $balanceGuard,
        private DocumentNumberGenerator $docGen,
    ) {}

    /**
     * Write off the uncollectable portion of an approved invoice.
     *
     * Writes off `$amount` (defaults to full outstanding due amount).
     *
     * GL:
     *   DR  bad_debt_account_id   — expense recognised
     *   CR  customer_account_id   — AR reduced
     *
     * The invoice gains `written_off_at`, `write_off_amount`, and `write_off_remarks`.
     * Subsequent due-amount queries should exclude written-off amounts.
     */
    public function writeOff(Invoice $invoice, float $amount, ?string $remarks = null, ?string $writtenOffAt = null): void
    {
        if ($invoice->status !== StatusEnum::APPROVED || $invoice->voided_at) {
            throw ValidationException::withMessages([
                'invoice' => 'Only approved, non-voided invoices can be written off.',
            ]);
        }

        if ($invoice->written_off_at) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice has already been written off.',
            ]);
        }

        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Write-off amount must be greater than zero.',
            ]);
        }

        // Outstanding due = total - paid - previous write-offs (none, since we guard above)
        $dueAmount = round(
            ((float) ($invoice->total_amount ?? 0)) - ((float) ($invoice->paid_amount ?? 0)),
            4,
        );

        if ($amount > $dueAmount + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'Write-off amount exceeds the invoice due amount.',
            ]);
        }

        $user = auth('admin')->user();
        $settings = $this->resolveSettings($invoice->company_id);
        $date = $writtenOffAt ?? now()->toDateString();
        $yearCode = $invoice->fiscalYear?->year_code;

        DB::transaction(function () use ($invoice, $amount, $date, $remarks, $user, $settings, $yearCode) {
            $voucherNo = $this->docGen->journalVoucher(
                JournalTypeEnum::JOURNAL_VOUCHER,
                'BDWO-',
                $invoice->fiscal_year_id,
                $yearCode,
            );

            $journal = \App\Models\Journal::withoutGlobalScopes()->create([
                'company_id' => $invoice->company_id,
                'fiscal_year_id' => $invoice->fiscal_year_id,
                'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
                'reference_type' => Invoice::class,
                'reference_id' => $invoice->id,
                'voucher_no' => $voucherNo,
                'date' => $date,
                'remarks' => $remarks ?? 'Bad debt write-off — '.$invoice->invoice_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            // DR Bad Debt Expense
            $journal->journalItems()->create([
                'account_id' => $settings->bad_debt_account_id,
                'dr_amount' => round($amount, 2),
                'cr_amount' => 0,
                'remarks' => 'Bad debt — '.$invoice->invoice_no,
            ]);

            // CR Accounts Receivable
            $journal->journalItems()->create([
                'account_id' => $settings->customer_account_id,
                'dr_amount' => 0,
                'cr_amount' => round($amount, 2),
                'remarks' => 'Write-off of '.$invoice->invoice_no,
            ]);

            $this->balanceGuard->assertBalanced($journal);

            $invoice->update([
                'written_off_at' => now(),
                'write_off_amount' => $amount,
                'write_off_remarks' => $remarks,
            ]);
        });
    }

    /** @throws ValidationException */
    private function resolveSettings(int $companyId): AccountSetting
    {
        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        if (! $settings?->bad_debt_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Bad Debt account is not configured in Account Settings.',
            ]);
        }

        if (! $settings->customer_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Accounts Receivable account is not configured in Account Settings.',
            ]);
        }

        return $settings;
    }
}
