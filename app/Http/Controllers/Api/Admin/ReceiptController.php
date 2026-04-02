<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Receipt;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ReceiptResource;
use App\Http\Requests\Api\Admin\ReceiptRequest;

class ReceiptController extends Controller
{
    /**
     * @Permissions("list_receipt", group="receipt", desc="List Receipt")
     */
    public function index(Request $request)
    {
        $receipts = Receipt::filter($request->all())
            ->with(['party', 'account', 'allocations'])
            ->latest('receipt_date')
            ->paginate($request->limit ?? 25);

        return ReceiptResource::collection($receipts);
    }

    /**
     * @Permissions("create_receipt", group="receipt", desc="Create Receipt")
     */
    public function store(ReceiptRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $receiptNo = $formData['receipt_no'] ?? $this->generateReceiptNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $allocations = collect($formData['allocations'] ?? [])
            ->filter(fn ($allocation) => (float) $allocation['amount'] > 0)
            ->values();

        if ($allocations->isEmpty()) {
            return response()->json([
                'message' => 'At least one allocation amount is required.',
            ], 422);
        }

        $invoiceIds = $allocations->pluck('invoice_id')->unique()->values()->all();
        $dueMap = $this->getInvoiceDueMap($invoiceIds, $formData['party_id']);

        foreach ($allocations as $allocation) {
            $invoiceId = (int) $allocation['invoice_id'];
            $amount = (float) $allocation['amount'];
            $due = $dueMap[$invoiceId]['due_amount'] ?? null;
            if ($due === null) {
                return response()->json([
                    'message' => 'Invoice does not belong to the selected party.',
                ], 422);
            }
            if ($amount > $due) {
                return response()->json([
                    'message' => 'Allocation amount exceeds invoice due amount.',
                ], 422);
            }
        }

        $receipt = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $receiptNo, $allocations) {
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

            $items = $allocations->map(function ($allocation) {
                return [
                    'invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                ];
            })->all();

            $receipt->allocations()->createMany($items);

            return $receipt;
        });

        $receipt->load(['party', 'account', 'allocations.invoice']);

        return response()->json([
            'data' => ReceiptResource::make($receipt),
            'message' => 'Receipt Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_receipt", group="receipt", desc="Show Receipt")
     */
    public function show(Receipt $receipt)
    {
        $receipt->load(['party', 'account', 'allocations.invoice']);

        return ReceiptResource::make($receipt);
    }

    /**
     * @Permissions("edit_receipt", group="receipt", desc="Edit Receipt")
     */
    public function update(ReceiptRequest $request, Receipt $receipt)
    {
        if ($receipt->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved receipts cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();
        $receiptNo = $formData['receipt_no'] ?? $receipt->receipt_no;

        $allocations = collect($formData['allocations'] ?? [])
            ->filter(fn ($allocation) => (float) $allocation['amount'] > 0)
            ->values();

        if ($allocations->isEmpty()) {
            return response()->json([
                'message' => 'At least one allocation amount is required.',
            ], 422);
        }

        $invoiceIds = $allocations->pluck('invoice_id')->unique()->values()->all();
        $dueMap = $this->getInvoiceDueMap($invoiceIds, $formData['party_id']);

        foreach ($allocations as $allocation) {
            $invoiceId = (int) $allocation['invoice_id'];
            $amount = (float) $allocation['amount'];
            $due = $dueMap[$invoiceId]['due_amount'] ?? null;
            if ($due === null) {
                return response()->json([
                    'message' => 'Invoice does not belong to the selected party.',
                ], 422);
            }
            if ($amount > $due) {
                return response()->json([
                    'message' => 'Allocation amount exceeds invoice due amount.',
                ], 422);
            }
        }

        $receipt = DB::transaction(function () use ($receipt, $formData, $receiptNo, $allocations) {
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

            $items = $allocations->map(function ($allocation) {
                return [
                    'invoice_id' => $allocation['invoice_id'],
                    'amount' => $allocation['amount'],
                ];
            })->all();

            $receipt->allocations()->createMany($items);

            return $receipt;
        });

        $receipt->load(['party', 'account', 'allocations.invoice']);

        return response()->json([
            'data' => ReceiptResource::make($receipt),
            'message' => 'Receipt Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_receipt", group="receipt", desc="Delete Receipt")
     */
    public function destroy(Receipt $receipt)
    {
        $receipt->allocations()->delete();
        $receipt->delete();

        return response()->json([
            'message' => 'Receipt Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_receipt", group="receipt", desc="Approve Receipt")
     */
    public function approve(Receipt $receipt)
    {
        if ($receipt->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => ReceiptResource::make($receipt),
                'message' => 'Receipt Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $receipt->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $receipt->load(['party', 'account', 'allocations.invoice']);

        return response()->json([
            'data' => ReceiptResource::make($receipt),
            'message' => 'Receipt Approved Successfully',
        ]);
    }

    private function getInvoiceDueMap(array $invoiceIds, int $partyId): array
    {
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
            ->whereIn('invoices.id', $invoiceIds)
            ->where('invoices.party_id', $partyId)
            ->where('invoices.status', StatusEnum::APPROVED->value)
            ->whereNull('invoices.deleted_at')
            ->select([
                'invoices.id',
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

    private function generateReceiptNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Receipt::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'RC-'.($count + 1).$suffix;
    }
}
