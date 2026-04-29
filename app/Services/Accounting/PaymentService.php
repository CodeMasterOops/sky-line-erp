<?php

namespace App\Services\Accounting;

use App\Models\Payment;
use App\Enums\StatusEnum;
use App\Enums\JournalTypeEnum;
use App\Models\AccountSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Exceptions\HttpResponseException;

readonly class PaymentService
{
    public function createPayment(array $formData): Payment
    {
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $paymentNo = $formData['payment_no'] ?? $this->generatePaymentNo($fiscalYearId, $setting->fiscalYear?->year_code);
        $allocations = $this->validatedAllocations($formData);

        return DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $paymentNo, $allocations) {
            $payment = Payment::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'payment_no' => $paymentNo,
                'payment_date' => $formData['payment_date'],
                'payment_mode_id' => $formData['payment_mode_id'],
                'account_id' => $formData['account_id'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $payment->allocations()->createMany($this->mapAllocations($allocations));

            if ($status === StatusEnum::APPROVED->value) {
                $this->createJournal($payment);
            }

            return $payment;
        });
    }

    public function updatePayment(array $formData, Payment $payment): void
    {
        $paymentNo = $formData['payment_no'] ?? $payment->payment_no;
        $allocations = $this->validatedAllocations($formData);

        DB::transaction(function () use ($payment, $formData, $paymentNo, $allocations) {
            $payment->update([
                'party_id' => $formData['party_id'] ?? null,
                'payment_no' => $paymentNo,
                'payment_date' => $formData['payment_date'],
                'payment_mode_id' => $formData['payment_mode_id'],
                'account_id' => $formData['account_id'],
                'reference_no' => $formData['reference_no'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $payment->allocations()->delete();
            $payment->allocations()->createMany($this->mapAllocations($allocations));
        });
    }

    public function approvePayment(Payment $payment): void
    {
        $user = auth('admin')->user();

        DB::transaction(function () use ($payment, $user) {
            $payment->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->createJournal($payment);
        });
    }

    private function createJournal(Payment $payment): void
    {
        $payment->loadMissing('party:id,name', 'account')
            ->loadSum('allocations', 'amount');

        $accountSetting = AccountSetting::first();

        $journal = $payment->journal()->create([
            'fiscal_year_id' => $payment->fiscal_year_id,
            'type' => JournalTypeEnum::PAYMENT->value,
            'voucher_no' => $payment->payment_no,
            'reference_no' => null,
            'date' => $payment->payment_date,
            'remarks' => $payment->remarks,
            'create_user_id' => $payment->create_user_id,
            'approve_user_id' => $payment->approve_user_id,
            'approved_at' => $payment->approved_at,
            'status' => StatusEnum::APPROVED->value,
        ]);

        $paidAmount = $payment->allocations_sum_amount;

        // debit for account payable/supplier
        $journal->journalItems()->create([
            'account_id' => $accountSetting->supplier_account_id,
            'dr_amount' => $paidAmount,
            'cr_amount' => 0,
            'remarks' => 'By-'.($payment->party->name ?? ''),
        ]);

        // credit for paid account
        $journal->journalItems()->create([
            'account_id' => $payment->account_id,
            'dr_amount' => 0,
            'cr_amount' => $paidAmount,
            'remarks' => 'By-'.($payment->account->name ?? ''),
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

        $billIds = $allocations->where('payable_type', 'bill')->pluck('payable_id')->unique()->values()->all();
        $expenseIds = $allocations->where('payable_type', 'expense')->pluck('payable_id')->unique()->values()->all();
        $billDueMap = $this->getBillDueMap($billIds, (int) $formData['party_id']);
        $expenseDueMap = $this->getExpenseDueMap($expenseIds, (int) $formData['party_id']);

        foreach ($allocations as $allocation) {
            $type = $allocation['payable_type'];
            $payableId = (int) $allocation['payable_id'];
            $amount = (float) $allocation['amount'];
            $dueMap = $type === 'expense' ? $expenseDueMap : $billDueMap;
            $due = $dueMap[$payableId]['due_amount'] ?? null;

            if ($due === null) {
                $this->throwUnprocessableEntity('Allocation does not belong to the selected party.');
            }

            if ($amount > $due) {
                $this->throwUnprocessableEntity('Allocation amount exceeds due amount.');
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
                'payable_type' => $allocation['payable_type'],
                'payable_id' => $allocation['payable_id'],
                'amount' => $allocation['amount'],
            ];
        })->all();
    }

    private function getBillDueMap(array $billIds, int $partyId): array
    {
        if (empty($billIds)) {
            return [];
        }

        $itemsSub = DB::table('bill_items')
            ->selectRaw('bill_id, SUM(quantity * rate) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');

        $paidSub = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->selectRaw('payment_allocations.payable_id, SUM(payment_allocations.amount) as paid_total')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('payments.status', StatusEnum::APPROVED->value)
            ->where('payment_allocations.payable_type', 'bill')
            ->groupBy('payment_allocations.payable_id');

        $rows = DB::table('bills')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('bills.id', '=', 'item_totals.bill_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('bills.id', '=', 'paid_totals.payable_id');
            })
            ->whereIn('bills.id', $billIds)
            ->where('bills.party_id', $partyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.deleted_at')
            ->select([
                'bills.id',
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $grandTotal = (float) $row->subtotal - (float) $row->discount_total + (float) $row->tax_total;
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

    private function getExpenseDueMap(array $expenseIds, int $partyId): array
    {
        if (empty($expenseIds)) {
            return [];
        }

        $itemsSub = DB::table('expense_items')
            ->selectRaw('expense_id, SUM(amount) as subtotal, SUM(discount_amount) as discount_total, SUM(tax_amount) as tax_total')
            ->whereNull('deleted_at')
            ->groupBy('expense_id');

        $paidSub = DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->selectRaw('payment_allocations.payable_id, SUM(payment_allocations.amount) as paid_total')
            ->whereNull('payment_allocations.deleted_at')
            ->whereNull('payments.deleted_at')
            ->where('payments.status', StatusEnum::APPROVED->value)
            ->where('payment_allocations.payable_type', 'expense')
            ->groupBy('payment_allocations.payable_id');

        $rows = DB::table('expenses')
            ->leftJoinSub($itemsSub, 'item_totals', function ($join) {
                $join->on('expenses.id', '=', 'item_totals.expense_id');
            })
            ->leftJoinSub($paidSub, 'paid_totals', function ($join) {
                $join->on('expenses.id', '=', 'paid_totals.payable_id');
            })
            ->whereIn('expenses.id', $expenseIds)
            ->where('expenses.party_id', $partyId)
            ->where('expenses.status', StatusEnum::APPROVED->value)
            ->whereNull('expenses.deleted_at')
            ->select([
                'expenses.id',
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $grandTotal = (float) $row->subtotal - (float) $row->discount_total + (float) $row->tax_total;
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

    private function generatePaymentNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Payment::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'PP-'.($count + 1).$suffix;
    }
}
