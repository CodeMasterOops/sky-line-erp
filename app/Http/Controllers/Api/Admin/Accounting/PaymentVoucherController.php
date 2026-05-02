<?php

namespace App\Http\Controllers\Api\Admin\Accounting;

use App\Models\Journal;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Accounting\PaymentVoucherResource;
use App\Http\Requests\Api\Admin\Accounting\PaymentVoucherRequest;

class PaymentVoucherController extends Controller
{
    /**
     * @Permissions("list_payment_voucher", group="payment_voucher", desc="List Payment Voucher")
     */
    public function index(Request $request)
    {
        $filters = $request->all();

        $vouchers = Journal::filter($filters)
            ->where('type', JournalTypeEnum::PAYMENT_VOUCHER->value)
            ->latest('date')
            ->paginate($request->limit ?? 25);

        return PaymentVoucherResource::collection($vouchers);
    }

    /**
     * @Permissions("create_payment_voucher", group="payment_voucher", desc="Create Payment Voucher")
     */
    public function store(PaymentVoucherRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        $voucherCount = Journal::where('type', JournalTypeEnum::PAYMENT_VOUCHER->value)
            ->where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();
        $voucherNo = 'PV-'.($voucherCount + 1).'/'.($setting->fiscalYear->year_code ?? '');

        $journal = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $voucherNo) {
            $journal = Journal::create([
                'fiscal_year_id' => $fiscalYearId,
                'type' => JournalTypeEnum::PAYMENT_VOUCHER->value,
                'voucher_no' => $voucherNo,
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = $this->buildJournalItems($formData);
            $journal->journalItems()->createMany($items);

            return $journal;
        });

        $journal->load(['journalItems.account']);

        return response()->json([
            'data' => PaymentVoucherResource::make($journal),
            'message' => 'Payment Voucher Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_payment_voucher", group="payment_voucher", desc="Show Payment Voucher")
     */
    public function show(Journal $paymentVoucher)
    {
        $this->ensurePaymentVoucher($paymentVoucher);

        $paymentVoucher->load(['journalItems.account']);

        return PaymentVoucherResource::make($paymentVoucher);
    }

    /**
     * @Permissions("edit_payment_voucher", group="payment_voucher", desc="Edit Payment Voucher")
     */
    public function update(PaymentVoucherRequest $request, Journal $paymentVoucher)
    {
        $this->ensurePaymentVoucher($paymentVoucher);

        if ($paymentVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved payment vouchers cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();

        $paymentVoucher = DB::transaction(function () use ($paymentVoucher, $formData) {
            $paymentVoucher->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $paymentVoucher->journalItems()->delete();

            $items = $this->buildJournalItems($formData);
            $paymentVoucher->journalItems()->createMany($items);

            return $paymentVoucher;
        });

        $paymentVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => PaymentVoucherResource::make($paymentVoucher),
            'message' => 'Payment Voucher Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_payment_voucher", group="payment_voucher", desc="Delete Payment Voucher")
     */
    public function destroy(Journal $paymentVoucher)
    {
        $this->ensurePaymentVoucher($paymentVoucher);

        $paymentVoucher->journalItems()->delete();
        $paymentVoucher->delete();

        return response()->json([
            'message' => 'Payment Voucher Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_payment_voucher", group="payment_voucher", desc="Approve Payment Voucher")
     */
    public function approve(Journal $paymentVoucher)
    {
        $this->ensurePaymentVoucher($paymentVoucher);

        if ($paymentVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => PaymentVoucherResource::make($paymentVoucher->load(['journalItems.account'])),
                'message' => 'Payment Voucher Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $paymentVoucher->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $paymentVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => PaymentVoucherResource::make($paymentVoucher),
            'message' => 'Payment Voucher Approved Successfully',
        ]);
    }

    private function buildJournalItems(array $formData): array
    {
        $items = collect($formData['items'] ?? [])->map(function ($item) {
            return [
                'account_id' => $item['account_id'],
                'dr_amount' => $item['amount'] ?? 0,
                'cr_amount' => 0,
                'remarks' => $item['remarks'] ?? null,
            ];
        })->all();

        $totalAmount = collect($items)->sum(function ($item) {
            return (float) ($item['dr_amount'] ?? 0);
        });

        $items[] = [
            'account_id' => $formData['paid_from_account_id'],
            'dr_amount' => 0,
            'cr_amount' => $totalAmount,
            'remarks' => $formData['remarks'] ?? null,
        ];

        return $items;
    }

    private function ensurePaymentVoucher(Journal $journal): void
    {
        if ($journal->type !== JournalTypeEnum::PAYMENT_VOUCHER) {
            abort(404);
        }
    }
}
