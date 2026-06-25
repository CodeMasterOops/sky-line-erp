<?php

namespace App\Services\Sales;

use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Models\TdsDeduction;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\DocumentNumberGenerator;
use App\Services\Nepal\NepaliDateService;
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
        private NepaliDateService $nepaliDate,
    ) {}

    public function isPosted(Receipt $receipt): bool
    {
        return Journal::withoutGlobalScopes()
            ->where('company_id', $receipt->company_id)
            ->where('reference_type', $receipt->getMorphClass())
            ->where('reference_id', $receipt->id)
            ->where('type', JournalTypeEnum::RECEIPT->value)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * Idempotently post a receipt journal. No-ops when already posted.
     */
    public function repost(Receipt $receipt): void
    {
        if ($this->isPosted($receipt)) {
            return;
        }

        DB::transaction(fn () => $this->createJournal($receipt));
    }

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
            $tdsData = $this->normalizeTdsData($formData);

            $receipt = Receipt::create([
                'company_id' => $user->company->id,
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'receipt_no' => $receiptNo,
                'receipt_date' => $formData['receipt_date'],
                'payment_method' => $formData['payment_method'],
                'account_id' => $formData['account_id'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
                'tds_category' => $tdsData['tds_category'],
                'tds_rate' => $tdsData['tds_rate'],
                'tds_amount' => $tdsData['tds_amount'],
            ]);

            $receipt->allocations()->createMany($this->mapAllocations($allocations));

            if ($status === StatusEnum::APPROVED->value) {
                $this->createJournal($receipt);
                $this->refreshInvoicePaidAmounts($allocations->pluck('invoice_id')->all());
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
            $tdsData = $this->normalizeTdsData($formData);

            $receipt->update([
                'party_id' => $formData['party_id'] ?? null,
                'receipt_no' => $receiptNo,
                'receipt_date' => $formData['receipt_date'],
                'payment_method' => $formData['payment_method'],
                'account_id' => $formData['account_id'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'tds_category' => $tdsData['tds_category'],
                'tds_rate' => $tdsData['tds_rate'],
                'tds_amount' => $tdsData['tds_amount'],
            ]);

            $receipt->allocations()->delete();
            $receipt->allocations()->createMany($this->mapAllocations($allocations));
        });
    }

    public function approveReceipt(Receipt $receipt): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($receipt, $user) {
            $invoiceIds = $receipt->allocations()->pluck('invoice_id')->all();

            $receipt->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($receipt);
            $this->refreshInvoicePaidAmounts($invoiceIds);
        });
    }

    public function voidReceipt(Receipt $receipt): void
    {
        if ($receipt->status !== StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'status' => 'Only approved receipts can be voided.',
            ]);
        }

        $invoiceIds = $receipt->allocations()->pluck('invoice_id')->all();

        DB::transaction(function () use ($receipt, $invoiceIds) {
            $this->journalVoid->reverseForReference($receipt);
            $receipt->allocations()->delete();
            $receipt->delete();
            $this->refreshInvoicePaidAmounts($invoiceIds);
        });
    }

    public function createJournal(Receipt $receipt): void
    {
        if ($receipt->journal()->withoutGlobalScopes()->exists()) {
            return;
        }

        $receipt->loadMissing('party:id,name', 'account')
            ->loadSum('allocations', 'amount');

        $accountSetting = AccountSetting::withoutGlobalScopes()
            ->where('company_id', $receipt->company_id)
            ->first();

        if (! $accountSetting?->customer_account_id) {
            throw ValidationException::withMessages([
                'account_setting' => 'Cannot post receipt journal: Accounts Receivable account is not configured.',
            ]);
        }

        $this->periodGuard->assertPostable($receipt->company_id, $receipt->fiscal_year_id, $receipt->receipt_date);

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

        $grossAmount = round((float) $receipt->allocations_sum_amount, 2);
        $tdsAmount = round((float) ($receipt->tds_amount ?? 0), 2);
        $cashDebit = round(max($grossAmount - $tdsAmount, 0), 2);

        $journal->journalItems()->create([
            'account_id' => $accountSetting->customer_account_id,
            'dr_amount' => 0,
            'cr_amount' => $grossAmount,
            'remarks' => 'To-'.($receipt->party->name ?? ''),
        ]);

        if ($cashDebit > 0) {
            $journal->journalItems()->create([
                'account_id' => $receipt->account_id,
                'dr_amount' => $cashDebit,
                'cr_amount' => 0,
                'remarks' => 'To-'.($receipt->account->name ?? ''),
            ]);
        }

        if ($tdsAmount > 0 && $accountSetting->tds_receivable_account_id) {
            $journal->journalItems()->create([
                'account_id' => $accountSetting->tds_receivable_account_id,
                'dr_amount' => $tdsAmount,
                'cr_amount' => 0,
                'remarks' => 'TDS receivable from '.($receipt->party->name ?? ''),
            ]);

            TdsDeduction::create([
                'company_id' => $receipt->company_id,
                'fiscal_year_id' => $receipt->fiscal_year_id,
                'deductible_type' => Receipt::class,
                'deductible_id' => $receipt->id,
                'party_id' => $receipt->party_id,
                'tds_category' => $receipt->tds_category,
                'base_amount' => $grossAmount,
                'tds_rate' => round((float) ($receipt->tds_rate ?? 0), 2),
                'tds_amount' => $tdsAmount,
                'period_month' => $this->derivePeriodMonth($receipt->receipt_date),
                'journal_id' => $journal->id,
            ]);
        }

        $this->balanceGuard->assertBalanced($journal);
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
            $due = $dueMap[$invoiceId]['due_amount'] ?? null;

            if ($due === null) {
                $this->throwUnprocessableEntity('Invoice does not belong to the selected party.');
            }

            if ($amount > $due) {
                $this->throwUnprocessableEntity('Allocation amount exceeds invoice due amount.');
            }
        }

        return $allocations;
    }

    /**
     * @return array{tds_category: ?string, tds_rate: ?float, tds_amount: float}
     */
    private function normalizeTdsData(array $formData): array
    {
        $tdsAmount = round((float) ($formData['tds_amount'] ?? 0), 4);

        if ($tdsAmount <= 0) {
            return ['tds_category' => null, 'tds_rate' => null, 'tds_amount' => 0];
        }

        return [
            'tds_category' => $formData['tds_category'] ?? null,
            'tds_rate' => isset($formData['tds_rate']) ? round((float) $formData['tds_rate'], 4) : null,
            'tds_amount' => $tdsAmount,
        ];
    }

    /** Converts an AD date string to a BS period in YYYY-MM format, e.g. "2081-07". */
    private function derivePeriodMonth(?string $adDate): ?string
    {
        if (! $adDate) {
            return null;
        }

        try {
            $bs = $this->nepaliDate->adToBs($adDate);

            return sprintf('%d-%02d', $bs['year'], $bs['month']);
        } catch (\Throwable) {
            return null;
        }
    }

    private function refreshInvoicePaidAmounts(array $invoiceIds): void
    {
        Invoice::withoutGlobalScopes()
            ->whereIn('id', array_unique($invoiceIds))
            ->get()
            ->each(fn (Invoice $inv) => $inv->refreshTotals());
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
            return [
                'invoice_id' => $allocation['invoice_id'],
                'amount' => $allocation['amount'],
            ];
        })->all();
    }

    private function getInvoiceDueMap(array $invoiceIds, int $partyId): array
    {
        if (empty($invoiceIds)) {
            return [];
        }

        // When total_amount is already stored (populated by InvoiceService::refreshTotals),
        // skip the items subquery and use the denormalized column directly. paid_amount is
        // always live-computed from allocations inside the lock to prevent over-allocation.
        $paidSub = DB::table('receipt_allocations')
            ->join('receipts', 'receipts.id', '=', 'receipt_allocations.receipt_id')
            ->selectRaw('receipt_allocations.invoice_id, SUM(receipt_allocations.amount) as receipt_paid')
            ->whereNull('receipt_allocations.deleted_at')
            ->whereNull('receipts.deleted_at')
            ->where('receipts.status', StatusEnum::APPROVED->value)
            ->groupBy('receipt_allocations.invoice_id');

        $advanceSub = DB::table('advance_applications')
            ->selectRaw('invoice_id, SUM(amount) as advance_paid')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $itemsSub = DB::table('invoice_items')
            ->selectRaw('invoice_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('invoice_id');

        $rows = DB::table('invoices')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('invoices.id', '=', 'item_totals.invoice_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('invoices.id', '=', 'paid_totals.invoice_id');
            })
            ->leftJoinSub($advanceSub, 'advance_totals', function ($join) {
                $join->on('invoices.id', '=', 'advance_totals.invoice_id');
            })
            ->leftJoin('discounts', function ($join) {
                $join->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', '=', (new Invoice)->getMorphClass());
            })
            ->whereIn('invoices.id', $invoiceIds)
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.voided_at')
            ->whereNull('invoices.deleted_at')
            ->lockForUpdate()
            ->select([
                'invoices.id',
                'invoices.total_amount',
                DB::raw('COALESCE(discounts.amount, 0) as order_discount_amount'),
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.receipt_paid, 0) + COALESCE(advance_totals.advance_paid, 0) as paid_total'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            if ($row->total_amount !== null) {
                $grandTotal = (float) $row->total_amount;
            } else {
                $orderDisc = (float) ($row->order_discount_amount ?? 0);
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total - $orderDisc + (float) $row->tax_total;
            }
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
