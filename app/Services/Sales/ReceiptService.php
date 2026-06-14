<?php

namespace App\Services\Sales;

use App\Models\Tax;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use App\Models\ReceiptPayment;
use App\Enums\ChequeStatusEnum;
use App\Enums\PaymentMethodEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use App\Services\Accounting\PeriodLockGuard;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\JournalVoidService;
use App\Services\Accounting\JournalBalanceGuard;
use Illuminate\Http\Exceptions\HttpResponseException;

readonly class ReceiptService
{
    public function __construct(
        private DocumentNumberGenerator $documentNumberGenerator,
        private PeriodLockGuard $periodGuard,
        private JournalBalanceGuard $balanceGuard,
        private JournalVoidService $journalVoid,
        private TdsService $tdsService,
    ) {}

    public function createReceipt(array $formData): Receipt
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $setting) {
            $allocations = $this->validatedAllocations($formData);
            // See InvoiceService for the lock-inside-transaction concurrency note.
            $receiptNo = $formData['receipt_no'] ?? $this->documentNumberGenerator->fiscalYear(
                Receipt::class,
                'RC-',
                $fiscalYearId,
                $setting->fiscalYear?->year_code,
            );

            $legData = $this->resolvePaymentLegs($formData);
            $firstLeg = $legData[0] ?? [];

            $receipt = Receipt::create([
                'company_id' => $user->company->id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'receipt_no' => $receiptNo,
                'receipt_date' => $formData['receipt_date'],
                'payment_method' => $firstLeg['payment_method'] ?? $formData['payment_method'] ?? null,
                'account_id' => $firstLeg['account_id'] ?? $formData['account_id'] ?? null,
                'reference_no' => $firstLeg['reference_no'] ?? $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $this->syncPaymentLegs($receipt, $legData);
            $receipt->allocations()->createMany($this->mapAllocations($allocations));

            if ($status === StatusEnum::APPROVED->value) {
                $this->createJournal($receipt);
                $this->recordTdsDeductions($receipt);
            }

            return $receipt;
        });
    }

    public function updateReceipt(array $formData, Receipt $receipt): void
    {
        if ($receipt->status === StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Approved receipts cannot be edited. Please void this receipt and create a new one.',
            ]);
        }

        $receiptNo = $formData['receipt_no'] ?? $receipt->receipt_no;

        DB::transaction(function () use ($receipt, $formData, $receiptNo) {
            $allocations = $this->validatedAllocations($formData);
            $legData = $this->resolvePaymentLegs($formData);
            $firstLeg = $legData[0] ?? [];

            $receipt->update([
                'party_id' => $formData['party_id'] ?? null,
                'receipt_no' => $receiptNo,
                'receipt_date' => $formData['receipt_date'],
                'payment_method' => $firstLeg['payment_method'] ?? $formData['payment_method'] ?? $receipt->payment_method,
                'account_id' => $firstLeg['account_id'] ?? $formData['account_id'] ?? $receipt->account_id,
                'reference_no' => $firstLeg['reference_no'] ?? $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $this->syncPaymentLegs($receipt, $legData);
            $receipt->allocations()->delete();
            $receipt->allocations()->createMany($this->mapAllocations($allocations));
        });
    }

    public function approveReceipt(Receipt $receipt): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($receipt, $user) {
            $receipt->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($receipt);
            $this->recordTdsDeductions($receipt);
        });
    }

    public function voidReceipt(Receipt $receipt): void
    {
        if ($receipt->status !== StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved receipts can be voided.',
            ]);
        }

        DB::transaction(function () use ($receipt) {
            $this->journalVoid->reverseForReference($receipt);
            $receipt->allocations()->delete();
            $receipt->delete();
        });
    }

    public function clearCheque(ReceiptPayment $payment): void
    {
        if ($payment->payment_method !== PaymentMethodEnum::Cheque) {
            throw ValidationException::withMessages([
                'payment_method' => 'Only cheque payments can be cleared.',
            ]);
        }

        if ($payment->cheque_status === ChequeStatusEnum::Cleared) {
            throw ValidationException::withMessages([
                'cheque_status' => 'This cheque has already been cleared.',
            ]);
        }

        DB::transaction(function () use ($payment) {
            $payment->update(['cheque_status' => ChequeStatusEnum::Cleared]);
        });
    }

    public function createJournal(Receipt $receipt): void
    {
        if ($receipt->journal()->withoutGlobalScopes()->exists()) {
            return;
        }

        $receipt->loadMissing('party:id,name', 'account', 'allocations', 'receiptPayments.account');

        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $receipt->company_id)
            ->first();

        if (! $accountSetting?->customer_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post receipt journal: Accounts Receivable account is not configured.',
            ]);
        }

        $this->periodGuard->assertPostable($receipt->company_id, $receipt->fiscal_year_id, $receipt->receipt_date);

        // AR credit = full allocation amounts (what the invoice was owed)
        $totalAllocated = round((float) $receipt->allocations->sum('amount'), 2);

        // TDS receivable = total TDS deducted by customer across all allocations
        $totalTdsDeducted = round((float) $receipt->allocations->sum('tds_deducted'), 2);

        // Cash/Bank DR = actual cash received (net of TDS)
        $netCashReceived = round($totalAllocated - $totalTdsDeducted, 2);

        $journal = $receipt->journal()->create([
            'company_id' => $receipt->company_id,
            'fiscal_year_id' => $receipt->fiscal_year_id,
            'type' => JournalTypeEnum::RECEIPT->value,
            'voucher_no' => $receipt->receipt_no,
            'reference_no' => null,
            'date' => $receipt->receipt_date,
            'remarks' => $receipt->remarks,
            'create_user_id' => $receipt->create_user_id,
            'approve_user_id' => $receipt->approve_user_id,
            'approved_at' => $receipt->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        // CR Accounts Receivable — full invoice amounts allocated
        $journal->journalItems()->create([
            'account_id' => $accountSetting->customer_account_id,
            'dr_amount' => 0,
            'cr_amount' => $totalAllocated,
            'remarks' => 'To-'.($receipt->party->name ?? ''),
        ]);

        // DR per payment leg — proportional share of net cash received
        $legs = $receipt->receiptPayments->filter(fn ($leg) => (float) $leg->amount > 0);
        if ($legs->isNotEmpty()) {
            foreach ($legs as $leg) {
                $legAmount = round((float) $leg->amount, 2);
                $journal->journalItems()->create([
                    'account_id' => $leg->account_id,
                    'dr_amount' => $legAmount,
                    'cr_amount' => 0,
                    'remarks' => 'To-'.($leg->account->name ?? ''),
                ]);
            }
        } elseif ($netCashReceived > 0) {
            // Fallback: single DR line using legacy receipt.account_id
            $journal->journalItems()->create([
                'account_id' => $receipt->account_id,
                'dr_amount' => $netCashReceived,
                'cr_amount' => 0,
                'remarks' => 'To-'.($receipt->account->name ?? ''),
            ]);
        }

        // DR TDS Receivable — TDS withheld by customer
        if ($totalTdsDeducted > 0) {
            if (! $accountSetting->tds_receivable_account_id) {
                throw ValidationException::withMessages([
                    'account_setting' => 'Cannot post TDS journal: TDS Receivable account is not configured.',
                ]);
            }

            $journal->journalItems()->create([
                'account_id' => $accountSetting->tds_receivable_account_id,
                'dr_amount' => $totalTdsDeducted,
                'cr_amount' => 0,
                'remarks' => 'TDS withheld – '.$receipt->receipt_no,
            ]);
        }

        $this->balanceGuard->assertBalanced($journal);
    }

    /**
     * Resolve payment legs from form data.
     * If `payments` array is provided, use it. Otherwise fall back to legacy single-leg.
     *
     * @return array<int, array<string, mixed>>
     */
    private function resolvePaymentLegs(array $formData): array
    {
        if (! empty($formData['payments']) && is_array($formData['payments'])) {
            return array_filter($formData['payments'], fn ($p) => (float) ($p['amount'] ?? 0) > 0);
        }

        // Legacy single payment — wrap in array, amount = net of TDS
        if (! empty($formData['account_id'])) {
            $totalAllocated = collect($formData['allocations'] ?? [])
                ->sum(fn ($a) => (float) ($a['amount'] ?? 0));
            $totalTdsDeducted = collect($formData['allocations'] ?? [])
                ->sum(fn ($a) => (float) ($a['tds_deducted'] ?? 0));

            return [[
                'payment_mode_id' => $formData['payment_mode_id'] ?? null,
                'payment_method' => $formData['payment_method'] ?? null,
                'account_id' => $formData['account_id'],
                'amount' => round($totalAllocated - $totalTdsDeducted, 2),
                'reference_no' => $formData['reference_no'] ?? null,
                'cheque_date' => null,
                'cheque_status' => null,
            ]];
        }

        return [];
    }

    /**
     * @param  array<int, array<string, mixed>>  $legs
     */
    private function syncPaymentLegs(Receipt $receipt, array $legs): void
    {
        $receipt->receiptPayments()->delete();

        foreach ($legs as $leg) {
            $chequeStatus = null;
            if (! empty($leg['cheque_status'])) {
                $chequeStatus = $leg['cheque_status'] instanceof ChequeStatusEnum
                    ? $leg['cheque_status']->value
                    : $leg['cheque_status'];
            } elseif (($leg['payment_method'] ?? null) === PaymentMethodEnum::Cheque->value
                || ($leg['payment_method'] ?? null) === 'cheque') {
                $chequeStatus = ChequeStatusEnum::Pending->value;
            }

            $receipt->receiptPayments()->create([
                'payment_mode_id' => $leg['payment_mode_id'] ?? null,
                'payment_method' => $leg['payment_method'] ?? null,
                'account_id' => $leg['account_id'],
                'amount' => $leg['amount'],
                'reference_no' => $leg['reference_no'] ?? null,
                'cheque_date' => $leg['cheque_date'] ?? null,
                'cheque_status' => $chequeStatus,
            ]);
        }
    }

    private function recordTdsDeductions(Receipt $receipt): void
    {
        $receipt->loadMissing('allocations');

        foreach ($receipt->allocations as $allocation) {
            if ((float) $allocation->tds_deducted <= 0 || ! $allocation->tds_id) {
                continue;
            }

            $tds = Tax::withoutGlobalScopes()->find($allocation->tds_id);
            if (! $tds) {
                continue;
            }

            $this->tdsService->recordDeductionOnReceipt($receipt, $allocation, $tds);
        }
    }

    private function validatedAllocations(array $formData): Collection
    {
        $allocations = collect($formData['allocations'] ?? [])
            ->filter(fn ($allocation) => (float) $allocation['amount'] > 0)
            ->values();

        if ($allocations->isEmpty()) {
            $this->throwUnprocessableEntity('At least one allocation amount is required.');
        }

        $invoiceIds = $allocations->pluck('invoice_id')->unique()->values()->all();
        $dueMap = $this->getInvoiceDueMap($invoiceIds, (int) $formData['party_id']);

        foreach ($allocations as $allocation) {
            $invoiceId = (int) $allocation['invoice_id'];
            $amount = (float) $allocation['amount'];
            $tdsDeducted = (float) ($allocation['tds_deducted'] ?? 0);
            $due = $dueMap[$invoiceId]['due_amount'] ?? null;

            if ($due === null) {
                $this->throwUnprocessableEntity('Invoice does not belong to the selected party.');
            }

            if ($amount > $due) {
                $this->throwUnprocessableEntity('Allocation amount exceeds invoice due amount.');
            }

            if ($tdsDeducted > $amount) {
                $this->throwUnprocessableEntity('TDS deducted cannot exceed the allocation amount.');
            }
        }

        return $allocations;
    }

    private function throwUnprocessableEntity(string $message): never
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }

    private function mapAllocations(Collection $allocations): array
    {
        return $allocations->map(function ($allocation) {
            $tdsDeducted = (float) ($allocation['tds_deducted'] ?? 0);
            $netReceived = (float) $allocation['amount'] - $tdsDeducted;

            return [
                'invoice_id' => $allocation['invoice_id'],
                'amount' => $allocation['amount'],
                'tds_id' => $allocation['tds_id'] ?? null,
                'tds_deducted' => $tdsDeducted,
                'tds_base_amount' => (float) ($allocation['tds_base_amount'] ?? 0),
                'net_amount_received' => $netReceived,
            ];
        })->all();
    }

    private function getInvoiceDueMap(array $invoiceIds, int $partyId): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        $itemsSub = DB::table('invoice_items')
            ->selectRaw('invoice_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as paid_total')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereNull('receipts.deleted_at')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->groupBy('receipt_allocations.invoice_id');

        $chargesSub = DB::table('invoice_charges')
            ->selectRaw('invoice_id, SUM(amount) + SUM(tax_amount) as charges_total')
            ->groupBy('invoice_id');

        $rows = DB::table('invoices')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('invoices.id', '=', 'item_totals.invoice_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('invoices.id', '=', 'paid_totals.invoice_id');
            })
            ->leftJoinSub($chargesSub, 'charge_totals', function ($join) {
                $join->on('invoices.id', '=', 'charge_totals.invoice_id');
            })
            ->leftJoin('discounts', function ($join) {
                $join->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', '=', \App\Models\Invoice::class);
            })
            ->whereIn('invoices.id', $invoiceIds)
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.deleted_at')
            ->lockForUpdate()
            ->select([
                'invoices.id',
                DB::raw('COALESCE(discounts.amount, 0) as order_discount_amount'),
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(charge_totals.charges_total, 0) as charges_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $orderDisc = (float) ($row->order_discount_amount ?? 0);
            $grandTotal = (float) $row->subtotal - (float) $row->discount_total - $orderDisc + (float) $row->tax_total + (float) ($row->charges_total ?? 0);
            $paidTotal = (float) $row->paid_total;
            $due = max($grandTotal - $paidTotal, 0);
            $map[$row->id] = [
                'grand_total' => round($grandTotal, 2),
                'paid_total' => round($paidTotal, 2),
                'due_amount' => round($due, 2),
            ];
        }

        return $map;
    }
}
