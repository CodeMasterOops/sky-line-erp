<?php

namespace App\Services\Sales;

use App\Models\Receipt;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

readonly class ReceiptService
{
    public function createReceipt(array $formData): Receipt
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $receiptNo = $formData['receipt_no'] ?? $this->generateReceiptNo($fiscalYearId, $setting->fiscalYear?->year_code);
        $allocations = $this->validatedAllocations($formData);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $receiptNo, $allocations) {
            $receipt = Receipt::create([
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
            ]);

            $receipt->allocations()->createMany($this->mapAllocations($allocations));

            if ($status === StatusEnum::APPROVED->value) {
                $this->createJournal($receipt);
            }

            return $receipt;
        });
    }

    public function updateReceipt(array $formData, Receipt $receipt): void
    {
        $receiptNo = $formData['receipt_no'] ?? $receipt->receipt_no;
        $allocations = $this->validatedAllocations($formData);

        DB::transaction(function () use ($receipt, $formData, $receiptNo, $allocations) {
            $receipt->update([
                'party_id' => $formData['party_id'] ?? null,
                'receipt_no' => $receiptNo,
                'receipt_date' => $formData['receipt_date'],
                'payment_method' => $formData['payment_method'],
                'account_id' => $formData['account_id'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

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
        });
    }

    private function createJournal(Receipt $receipt): void
    {
        $receipt->loadMissing('party:id,name', 'account')
            ->loadSum('allocations', 'amount');

        $accountSetting = AccountSetting::first();

        $journal = $receipt->journal()->create([
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

        $receivedAmount = $receipt->allocations_sum_amount;

        $journal->journalItems()->create([
            'account_id' => $accountSetting->customer_account_id,
            'dr_amount' => 0,
            'cr_amount' => $receivedAmount,
            'remarks' => 'To-'.($receipt->party->name ?? ''),
        ]);

        $journal->journalItems()->create([
            'account_id' => $receipt->account_id,
            'dr_amount' => $receivedAmount,
            'cr_amount' => 0,
            'remarks' => 'To-'.($receipt->account->name ?? ''),
        ]);
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

        $rows = DB::table('invoices')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('invoices.id', '=', 'item_totals.invoice_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('invoices.id', '=', 'paid_totals.invoice_id');
            })
            ->leftJoin('discounts', function ($join) {
                $join->on('invoices.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', '=', \App\Models\Invoice::class);
            })
            ->whereIn('invoices.id', $invoiceIds)
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.deleted_at')
            ->select([
                'invoices.id',
                DB::raw('COALESCE(discounts.amount, 0) as order_discount_amount'),
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $orderDisc = (float) ($row->order_discount_amount ?? 0);
            $grandTotal = (float) $row->subtotal - (float) $row->discount_total - $orderDisc + (float) $row->tax_total;
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

    private function generateReceiptNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Receipt::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'RC-'.($count + 1).$suffix;
    }
}
