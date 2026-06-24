<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\CustomerAdvance;
use App\Models\AdvanceApplication;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\JournalBalanceGuard;

readonly class CustomerAdvanceService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private JournalBalanceGuard $balanceGuard,
        private JournalVoidService $journalVoid,
    ) {}

    public function create(array $data): CustomerAdvance
    {
        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($data, $user, $setting, $fiscalYearId) {
            $advanceNo = $data['advance_no'] ?? $this->documentNumberGenerator->fiscalYear(
                CustomerAdvance::class,
                'ADV-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );

            return CustomerAdvance::create([
                'company_id' => $user->company_id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $data['party_id'],
                'advance_no' => $advanceNo,
                'advance_date' => $data['advance_date'],
                'amount' => round((float) $data['amount'], 4),
                'applied_amount' => 0,
                'payment_method' => $data['payment_method'],
                'account_id' => $data['account_id'],
                'reference_no' => $data['reference_no'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'status' => StatusEnum::DRAFT->value,
                'create_user_id' => $user->id,
            ]);
        });
    }

    /**
     * Approve and post GL:
     *   DR  account_id (bank/cash)              — advance received
     *   CR  customer_advance_account_id          — customer deposit liability
     */
    public function approve(CustomerAdvance $advance): void
    {
        if ($advance->status !== StatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft advances can be approved.',
            ]);
        }

        $user = auth('admin')->user();
        $settings = $this->resolveSettings($advance->company_id);

        DB::transaction(function () use ($advance, $user, $settings) {
            $advance->update([
                'status' => StatusEnum::APPROVED->value,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
            ]);

            $amount = round((float) $advance->amount, 2);

            $journal = $advance->journal()->create([
                'company_id' => $advance->company_id,
                'branch_id' => $advance->branch_id,
                'fiscal_year_id' => $advance->fiscal_year_id,
                'type' => JournalTypeEnum::RECEIPT->value,
                'reference_type' => CustomerAdvance::class,
                'reference_id' => $advance->id,
                'voucher_no' => $advance->advance_no,
                'date' => $advance->advance_date->toDateString(),
                'remarks' => $advance->remarks ?? 'Customer advance — '.$advance->advance_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            // DR bank/cash — received
            $journal->journalItems()->create([
                'account_id' => $advance->account_id,
                'dr_amount' => $amount,
                'cr_amount' => 0,
                'remarks' => 'Advance from '.($advance->party?->name ?? ''),
            ]);

            // CR customer advance liability
            $journal->journalItems()->create([
                'account_id' => $settings->customer_advance_account_id,
                'dr_amount' => 0,
                'cr_amount' => $amount,
                'remarks' => 'Customer deposit — '.$advance->advance_no,
            ]);

            $this->balanceGuard->assertBalanced($journal);
        });
    }

    /**
     * Apply an advance (or portion) against an approved invoice.
     *
     * GL:
     *   DR  customer_advance_account_id   — reduce liability
     *   CR  customer_account_id (AR)      — reduce receivable
     */
    public function apply(CustomerAdvance $advance, Invoice $invoice, float $amount, ?string $appliedAt = null): AdvanceApplication
    {
        if ($advance->status !== StatusEnum::APPROVED || $advance->voided_at) {
            throw ValidationException::withMessages([
                'status' => 'Only approved advances can be applied.',
            ]);
        }

        $amount = round($amount, 4);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Application amount must be greater than zero.',
            ]);
        }

        // Early check for fast user feedback (non-authoritative under concurrency).
        if ($amount > round($advance->remainingAmount(), 4)) {
            throw ValidationException::withMessages([
                'amount' => 'Application amount exceeds remaining advance balance.',
            ]);
        }

        if ($invoice->status !== StatusEnum::APPROVED || $invoice->voided_at) {
            throw ValidationException::withMessages([
                'invoice_id' => 'Only approved invoices can receive an advance application.',
            ]);
        }

        $user = auth('admin')->user();
        $settings = $this->resolveSettings($advance->company_id);
        $date = $appliedAt ?? now()->toDateString();

        return DB::transaction(function () use ($advance, $invoice, $amount, $date, $user, $settings) {
            // Re-read with a write-lock to prevent concurrent over-application.
            $advance = CustomerAdvance::withoutGlobalScopes()->lockForUpdate()->find($advance->id);
            if ($amount > round($advance->remainingAmount(), 4)) {
                throw ValidationException::withMessages([
                    'amount' => 'Application amount exceeds remaining advance balance.',
                ]);
            }

            $application = AdvanceApplication::create([
                'company_id' => $advance->company_id,
                'branch_id' => $advance->branch_id,
                'customer_advance_id' => $advance->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'applied_at' => $date,
                'apply_user_id' => $user->id,
            ]);

            $advance->increment('applied_amount', $amount);

            // Post offsetting GL
            $journal = \App\Models\Journal::withoutGlobalScopes()->create([
                'company_id' => $advance->company_id,
                'branch_id' => $advance->branch_id,
                'fiscal_year_id' => $advance->fiscal_year_id,
                'type' => JournalTypeEnum::JOURNAL_VOUCHER->value,
                'reference_type' => AdvanceApplication::class,
                'reference_id' => $application->id,
                'voucher_no' => 'ADVAPP-'.$application->id,
                'date' => $date,
                'remarks' => 'Advance application to invoice '.$invoice->invoice_no,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            // DR customer advance liability — reduce
            $journal->journalItems()->create([
                'account_id' => $settings->customer_advance_account_id,
                'dr_amount' => round($amount, 2),
                'cr_amount' => 0,
                'remarks' => 'Advance applied to '.$invoice->invoice_no,
            ]);

            // CR AR — reduce receivable
            $journal->journalItems()->create([
                'account_id' => $settings->customer_account_id,
                'dr_amount' => 0,
                'cr_amount' => round($amount, 2),
                'remarks' => 'Advance from '.$advance->advance_no,
            ]);

            $this->balanceGuard->assertBalanced($journal);

            // Refresh the invoice's paid_amount to reflect the advance application
            $invoice->refreshTotals();

            return $application;
        });
    }

    /**
     * Void an approved advance — reverses the GL entry. Only allowed if no applications exist.
     */
    public function void(CustomerAdvance $advance): void
    {
        if ($advance->status !== StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved advances can be voided.',
            ]);
        }

        if ($advance->applications()->exists()) {
            throw ValidationException::withMessages([
                'applications' => 'Cannot void an advance that has been applied to invoices.',
            ]);
        }

        DB::transaction(function () use ($advance) {
            $this->journalVoid->reverseForReference($advance);
            $advance->update(['voided_at' => now()]);
        });
    }

    /** @throws ValidationException */
    private function resolveSettings(int $companyId): AccountSetting
    {
        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->first();

        if (! $settings?->customer_advance_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Customer Advance account is not configured in Account Settings.',
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
