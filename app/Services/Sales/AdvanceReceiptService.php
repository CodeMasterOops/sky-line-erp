<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\AdvanceReceipt;
use App\Models\AdvanceAdjustment;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use App\Services\Accounting\PeriodLockGuard;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\JournalBalanceGuard;

readonly class AdvanceReceiptService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private PeriodLockGuard $periodGuard,
        private JournalBalanceGuard $balanceGuard,
        private JournalVoidService $journalVoid,
    ) {}

    public function createAdvance(array $formData): AdvanceReceipt
    {
        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $fiscalYearId, $setting) {
            $advanceNo = $formData['advance_no'] ?? $this->documentNumberGenerator->fiscalYear(
                AdvanceReceipt::class,
                'ADV-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );

            return AdvanceReceipt::create([
                'company_id' => $user->company->id,
                'branch_id' => $user->branch_id ?? null,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'],
                'advance_no' => $advanceNo,
                'advance_date' => $formData['advance_date'],
                'payment_method' => $formData['payment_method'],
                'account_id' => $formData['account_id'],
                'amount' => $formData['amount'],
                'adjusted_amount' => 0,
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'status' => StatusEnum::DRAFT->value,
            ]);
        });
    }

    public function updateAdvance(array $formData, AdvanceReceipt $advance): void
    {
        if ($advance->status !== StatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft advances can be edited.',
            ]);
        }

        DB::transaction(function () use ($advance, $formData) {
            $advance->update([
                'party_id' => $formData['party_id'],
                'advance_date' => $formData['advance_date'],
                'payment_method' => $formData['payment_method'],
                'account_id' => $formData['account_id'],
                'amount' => $formData['amount'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);
        });
    }

    public function approveAdvance(AdvanceReceipt $advance): void
    {
        if ($advance->status !== StatusEnum::DRAFT) {
            throw ValidationException::withMessages([
                'status' => 'Only draft advances can be approved.',
            ]);
        }

        $user = auth('admin')->user();

        DB::transaction(function () use ($advance, $user) {
            $advance->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->postApprovalJournal($advance);
        });
    }

    public function adjustToInvoice(array $formData, AdvanceReceipt $advance): AdvanceAdjustment
    {
        if ($advance->status !== StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved advances can be adjusted against invoices.',
            ]);
        }

        $amount = round((float) ($formData['amount'] ?? 0), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Adjustment amount must be greater than zero.',
            ]);
        }

        $balance = round((float) $advance->amount - (float) $advance->adjusted_amount, 2);

        if ($amount > $balance) {
            throw ValidationException::withMessages([
                'amount' => "Adjustment amount ({$amount}) exceeds available advance balance ({$balance}).",
            ]);
        }

        $user = auth('admin')->user();

        return DB::transaction(function () use ($formData, $advance, $amount, $user) {
            $invoice = Invoice::findOrFail($formData['invoice_id']);

            $adjustment = AdvanceAdjustment::create([
                'advance_receipt_id' => $advance->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'adjusted_at' => now(),
                'create_user_id' => $user->id,
            ]);

            $advance->increment('adjusted_amount', $amount);

            $this->postAdjustmentJournal($adjustment, $advance, $invoice);

            return $adjustment;
        });
    }

    public function voidAdvance(AdvanceReceipt $advance): void
    {
        if ($advance->status === StatusEnum::DRAFT) {
            DB::transaction(function () use ($advance) {
                $advance->delete();
            });

            return;
        }

        if ($advance->status !== StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only draft or approved advances can be voided.',
            ]);
        }

        if ((float) $advance->adjusted_amount > 0) {
            throw ValidationException::withMessages([
                'status' => 'Cannot void an advance that has been partially or fully adjusted. Reverse the adjustments first.',
            ]);
        }

        DB::transaction(function () use ($advance) {
            $this->journalVoid->reverseForReference($advance);
            $advance->delete();
        });
    }

    private function postApprovalJournal(AdvanceReceipt $advance): void
    {
        $advance->loadMissing('party:id,name', 'account:id,name');

        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $advance->company_id)
            ->first();

        if (! $accountSetting?->advance_from_customers_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post advance journal: "Advance from Customers" account is not configured.',
            ]);
        }

        $this->periodGuard->assertPostable($advance->company_id, $advance->fiscal_year_id, $advance->advance_date);

        $amount = round((float) $advance->amount, 2);
        $partyName = $advance->party->name ?? '';

        $journal = $advance->journal()->create([
            'company_id' => $advance->company_id,
            'fiscal_year_id' => $advance->fiscal_year_id,
            'type' => JournalTypeEnum::ADVANCE_RECEIPT->value,
            'voucher_no' => $advance->advance_no,
            'reference_no' => $advance->reference_no,
            'date' => $advance->advance_date,
            'remarks' => $advance->remarks,
            'create_user_id' => $advance->create_user_id,
            'approve_user_id' => $advance->approve_user_id,
            'approved_at' => $advance->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        // DR Cash/Bank — cash received
        $journal->journalItems()->create([
            'account_id' => $advance->account_id,
            'dr_amount' => $amount,
            'cr_amount' => 0,
            'remarks' => 'Advance from '.$partyName,
        ]);

        // CR Advance from Customers (liability)
        $journal->journalItems()->create([
            'account_id' => $accountSetting->advance_from_customers_account_id,
            'dr_amount' => 0,
            'cr_amount' => $amount,
            'remarks' => 'Advance from '.$partyName.' – '.$advance->advance_no,
        ]);

        $this->balanceGuard->assertBalanced($journal);
    }

    private function postAdjustmentJournal(AdvanceAdjustment $adjustment, AdvanceReceipt $advance, Invoice $invoice): void
    {
        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $advance->company_id)
            ->first();

        if (! $accountSetting?->advance_from_customers_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post adjustment journal: "Advance from Customers" account is not configured.',
            ]);
        }

        if (! $accountSetting?->customer_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post adjustment journal: Accounts Receivable account is not configured.',
            ]);
        }

        $this->periodGuard->assertPostable($advance->company_id, $advance->fiscal_year_id, now()->toDateString());

        $amount = round((float) $adjustment->amount, 2);
        $partyName = $advance->party->name ?? '';

        $journal = $advance->journal()->create([
            'company_id' => $advance->company_id,
            'fiscal_year_id' => $advance->fiscal_year_id,
            'type' => JournalTypeEnum::ADVANCE_ADJUSTMENT->value,
            'voucher_no' => $advance->advance_no.'-ADJ',
            'reference_no' => $invoice->invoice_no ?? null,
            'date' => now()->toDateString(),
            'remarks' => 'Advance adjustment against invoice '.$invoice->invoice_no,
            'create_user_id' => $adjustment->create_user_id,
            'approve_user_id' => $adjustment->create_user_id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        // DR Advance from Customers — reduce liability
        $journal->journalItems()->create([
            'account_id' => $accountSetting->advance_from_customers_account_id,
            'dr_amount' => $amount,
            'cr_amount' => 0,
            'remarks' => 'Advance adjustment – '.$partyName,
        ]);

        // CR Accounts Receivable — reduce AR (invoice partially settled)
        $journal->journalItems()->create([
            'account_id' => $accountSetting->customer_account_id,
            'dr_amount' => 0,
            'cr_amount' => $amount,
            'remarks' => 'Applied to '.$invoice->invoice_no,
        ]);

        $this->balanceGuard->assertBalanced($journal);

        $adjustment->update(['journal_id' => $journal->id]);
    }
}
