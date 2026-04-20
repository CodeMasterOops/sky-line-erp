<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Journal;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\JournalTypeEnum;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ReceiptVoucherResource;
use App\Http\Requests\Api\Admin\ReceiptVoucherRequest;

class ReceiptVoucherController extends Controller
{
    /**
     * @Permissions("list_receipt_voucher", group="receipt_voucher", desc="List Receipt Voucher")
     */
    public function index(Request $request)
    {
        $filters = $request->all();

        $vouchers = Journal::filter($filters)
            ->where('type', JournalTypeEnum::RECEIPT_VOUCHER->value)
            ->latest('date')
            ->paginate($request->limit ?? 25);

        return ReceiptVoucherResource::collection($vouchers);
    }

    /**
     * @Permissions("create_receipt_voucher", group="receipt_voucher", desc="Create Receipt Voucher")
     */
    public function store(ReceiptVoucherRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;

        $voucherCount = Journal::where('type', JournalTypeEnum::RECEIPT_VOUCHER->value)
            ->where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();
        $voucherNo = 'RV-'.($voucherCount + 1).'/'.($setting->fiscalYear->year_code ?? '');

        $journal = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $voucherNo) {
            $journal = Journal::create([
                'fiscal_year_id' => $fiscalYearId,
                'type' => JournalTypeEnum::RECEIPT_VOUCHER->value,
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
            'data' => ReceiptVoucherResource::make($journal),
            'message' => 'Receipt Voucher Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_receipt_voucher", group="receipt_voucher", desc="Show Receipt Voucher")
     */
    public function show(Journal $receiptVoucher)
    {
        $this->ensureReceiptVoucher($receiptVoucher);

        $receiptVoucher->load(['journalItems.account']);

        return ReceiptVoucherResource::make($receiptVoucher);
    }

    /**
     * @Permissions("edit_receipt_voucher", group="receipt_voucher", desc="Edit Receipt Voucher")
     */
    public function update(ReceiptVoucherRequest $request, Journal $receiptVoucher)
    {
        $this->ensureReceiptVoucher($receiptVoucher);

        if ($receiptVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved receipt vouchers cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();

        $receiptVoucher = DB::transaction(function () use ($receiptVoucher, $formData) {
            $receiptVoucher->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $receiptVoucher->journalItems()->delete();

            $items = $this->buildJournalItems($formData);
            $receiptVoucher->journalItems()->createMany($items);

            return $receiptVoucher;
        });

        $receiptVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => ReceiptVoucherResource::make($receiptVoucher),
            'message' => 'Receipt Voucher Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_receipt_voucher", group="receipt_voucher", desc="Delete Receipt Voucher")
     */
    public function destroy(Journal $receiptVoucher)
    {
        $this->ensureReceiptVoucher($receiptVoucher);

        $receiptVoucher->journalItems()->delete();
        $receiptVoucher->delete();

        return response()->json([
            'message' => 'Receipt Voucher Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_receipt_voucher", group="receipt_voucher", desc="Approve Receipt Voucher")
     */
    public function approve(Journal $receiptVoucher)
    {
        $this->ensureReceiptVoucher($receiptVoucher);

        if ($receiptVoucher->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => ReceiptVoucherResource::make($receiptVoucher->load(['journalItems.account'])),
                'message' => 'Receipt Voucher Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $receiptVoucher->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $receiptVoucher->load(['journalItems.account']);

        return response()->json([
            'data' => ReceiptVoucherResource::make($receiptVoucher),
            'message' => 'Receipt Voucher Approved Successfully',
        ]);
    }

    private function buildJournalItems(array $formData): array
    {
        $items = collect($formData['items'] ?? [])->map(function ($item) {
            return [
                'account_id' => $item['account_id'],
                'dr_amount' => 0,
                'cr_amount' => $item['amount'] ?? 0,
                'remarks' => $item['remarks'] ?? null,
            ];
        })->all();

        $totalAmount = collect($items)->sum(function ($item) {
            return (float) ($item['cr_amount'] ?? 0);
        });

        $items[] = [
            'account_id' => $formData['deposited_to_account_id'],
            'dr_amount' => $totalAmount,
            'cr_amount' => 0,
            'remarks' => $formData['remarks'] ?? null,
        ];

        return $items;
    }

    private function ensureReceiptVoucher(Journal $journal): void
    {
        if ($journal->type !== JournalTypeEnum::RECEIPT_VOUCHER) {
            abort(404);
        }
    }
}
