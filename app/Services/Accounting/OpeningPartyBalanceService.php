<?php

namespace App\Services\Accounting;

use App\Models\Bill;
use App\Models\Party;
use App\Models\Invoice;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\JournalItem;
use App\Enums\PartyTypeEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;

/**
 * Records per-party opening balances the QuickBooks/Xero way: each customer or
 * supplier opening figure becomes a flagged, header-only opening Invoice / Bill
 * (no line items, so inventory is never touched) contra to the Opening Balance
 * Equity account. The document is a settleable open item, so the existing aging,
 * statement and receipt/payment settlement logic all apply unchanged.
 */
class OpeningPartyBalanceService
{
    public function __construct(
        private readonly DocumentNumberGenerator $documentNumberGenerator,
    ) {}

    /**
     * @param  array<int, array{party_id: int, amount: int|float, remarks?: string|null}>  $lines
     * @return array<int, Invoice>
     */
    public function postCustomers(array $lines, string $asOfDate, ?int $fiscalYearId = null): array
    {
        return $this->post(PartyTypeEnum::CUSTOMER, $lines, $asOfDate, $fiscalYearId);
    }

    /**
     * @param  array<int, array{party_id: int, amount: int|float, remarks?: string|null}>  $lines
     * @return array<int, Bill>
     */
    public function postSuppliers(array $lines, string $asOfDate, ?int $fiscalYearId = null): array
    {
        return $this->post(PartyTypeEnum::SUPPLIER, $lines, $asOfDate, $fiscalYearId);
    }

    /**
     * @param  array<int, array{party_id: int, amount: int|float, remarks?: string|null}>  $lines
     * @return array<int, Invoice|Bill>
     */
    private function post(PartyTypeEnum $type, array $lines, string $asOfDate, ?int $fiscalYearId): array
    {
        $user = auth('admin')->user();
        $company = $user->company;
        $fiscalYearId ??= $company->fiscal_year_id;

        $settings = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->first();

        $controlAccountId = $type === PartyTypeEnum::CUSTOMER
            ? $settings?->customer_account_id
            : $settings?->supplier_account_id;

        $equityAccountId = $settings?->opening_balance_equity_account_id ?? $settings?->suspense_account_id;

        if (! $controlAccountId || ! $equityAccountId) {
            throw ValidationException::withMessages([
                'account_setting' => 'Configure the '.($type === PartyTypeEnum::CUSTOMER ? 'customer' : 'supplier')
                    .' control account and an Opening Balance Equity (or Suspense) account before posting opening balances.',
            ]);
        }

        $rows = collect($lines)
            ->map(fn ($line) => [
                'party_id' => (int) $line['party_id'],
                'amount' => round((float) $line['amount'], 2),
                'remarks' => $line['remarks'] ?? null,
            ])
            ->filter(fn ($line) => $line['amount'] > 0)
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'lines' => 'Enter a positive opening amount for at least one party.',
            ]);
        }

        return DB::transaction(function () use ($type, $rows, $asOfDate, $fiscalYearId, $user, $company, $controlAccountId, $equityAccountId) {
            return $rows->map(function ($line) use ($type, $asOfDate, $fiscalYearId, $user, $company, $controlAccountId, $equityAccountId) {
                $party = Party::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('type', $type)
                    ->find($line['party_id']);

                if (! $party) {
                    throw ValidationException::withMessages([
                        'lines' => 'One of the selected parties is not a valid '.($type === PartyTypeEnum::CUSTOMER ? 'customer' : 'supplier').'.',
                    ]);
                }

                $this->assertNoExistingOpening($type, $company->id, $party->id, $party->name);

                return $type === PartyTypeEnum::CUSTOMER
                    ? $this->createCustomerOpening($party, $line, $asOfDate, $fiscalYearId, $user, $company, $controlAccountId, $equityAccountId)
                    : $this->createSupplierOpening($party, $line, $asOfDate, $fiscalYearId, $user, $company, $controlAccountId, $equityAccountId);
            })->all();
        });
    }

    /**
     * @param  array{party_id: int, amount: float, remarks: string|null}  $line
     */
    private function createCustomerOpening(Party $party, array $line, string $asOfDate, int $fiscalYearId, $user, $company, int $controlAccountId, int $equityAccountId): Invoice
    {
        $invoiceNo = $this->documentNumberGenerator->fiscalYear(
            Invoice::class,
            'OB-INV-',
            $fiscalYearId,
            $company->fiscalYear?->year_code,
        );

        $invoice = Invoice::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYearId,
            'party_id' => $party->id,
            'invoice_no' => $invoiceNo,
            'invoice_date' => $asOfDate,
            'due_date' => $asOfDate,
            'remarks' => $line['remarks'] ?? 'Opening balance',
            'create_user_id' => $user->id,
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
            'is_opening' => true,
            'opening_amount' => $line['amount'],
            'ird_sync_status' => 'skipped',
        ]);

        $this->postJournal(
            reference: $invoice,
            referenceNo: $invoice->invoice_no,
            date: $asOfDate,
            fiscalYearId: $fiscalYearId,
            company: $company,
            user: $user,
            debitAccountId: $controlAccountId,
            creditAccountId: $equityAccountId,
            amount: $line['amount'],
            remarks: 'Opening balance – '.$party->name,
        );

        return $invoice;
    }

    /**
     * @param  array{party_id: int, amount: float, remarks: string|null}  $line
     */
    private function createSupplierOpening(Party $party, array $line, string $asOfDate, int $fiscalYearId, $user, $company, int $controlAccountId, int $equityAccountId): Bill
    {
        $billNo = $this->documentNumberGenerator->fiscalYear(
            Bill::class,
            'OB-BILL-',
            $fiscalYearId,
            $company->fiscalYear?->year_code,
        );

        $bill = Bill::create([
            'company_id' => $company->id,
            'fiscal_year_id' => $fiscalYearId,
            'party_id' => $party->id,
            'bill_no' => $billNo,
            'bill_date' => $asOfDate,
            'due_date' => $asOfDate,
            'remarks' => $line['remarks'] ?? 'Opening balance',
            'create_user_id' => $user->id,
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
            'is_opening' => true,
            'opening_amount' => $line['amount'],
        ]);

        $this->postJournal(
            reference: $bill,
            referenceNo: $bill->bill_no,
            date: $asOfDate,
            fiscalYearId: $fiscalYearId,
            company: $company,
            user: $user,
            debitAccountId: $equityAccountId,
            creditAccountId: $controlAccountId,
            amount: $line['amount'],
            remarks: 'Opening balance – '.$party->name,
        );

        return $bill;
    }

    private function postJournal(
        Invoice|Bill $reference,
        string $referenceNo,
        string $date,
        int $fiscalYearId,
        $company,
        $user,
        int $debitAccountId,
        int $creditAccountId,
        float $amount,
        string $remarks,
    ): void {
        $voucherNo = $this->documentNumberGenerator->journalVoucher(
            JournalTypeEnum::OPENING_BALANCE,
            'OB-',
            $fiscalYearId,
            $company->fiscalYear?->year_code,
        );

        $journal = Journal::withoutGlobalScopes()->create([
            'company_id' => $company->id,
            'branch_id' => $reference->branch_id,
            'fiscal_year_id' => $fiscalYearId,
            'type' => JournalTypeEnum::OPENING_BALANCE->value,
            'reference_type' => $reference->getMorphClass(),
            'reference_id' => $reference->id,
            'voucher_no' => $voucherNo,
            'reference_no' => $referenceNo,
            'date' => $date,
            'remarks' => $remarks,
            'create_user_id' => $user->id,
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        JournalItem::create([
            'journal_id' => $journal->id,
            'account_id' => $debitAccountId,
            'dr_amount' => $amount,
            'cr_amount' => 0,
            'remarks' => $remarks,
        ]);

        JournalItem::create([
            'journal_id' => $journal->id,
            'account_id' => $creditAccountId,
            'dr_amount' => 0,
            'cr_amount' => $amount,
            'remarks' => $remarks,
        ]);
    }

    private function assertNoExistingOpening(PartyTypeEnum $type, int $companyId, int $partyId, string $partyName): void
    {
        $model = $type === PartyTypeEnum::CUSTOMER ? Invoice::class : Bill::class;

        $exists = $model::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('party_id', $partyId)
            ->where('is_opening', true)
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'lines' => "An opening balance already exists for '{$partyName}'. Void it before posting a new one.",
            ]);
        }
    }
}
