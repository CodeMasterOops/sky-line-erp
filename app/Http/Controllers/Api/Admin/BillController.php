<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Bill;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BillResource;
use App\Http\Requests\Api\Admin\BillRequest;

class BillController extends Controller
{
    /**
     * @Permissions("list_bill", group="bill", desc="List Bill")
     */
    public function index(Request $request)
    {
        $bills = Bill::filter($request->all())
            ->with(['party', 'billItems'])
            ->latest('bill_date')
            ->paginate($request->limit ?? 25);

        return BillResource::collection($bills);
    }

    /**
     * @Permissions("create_bill", group="bill", desc="Create Bill")
     */
    public function store(BillRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $billNo = $formData['bill_no'] ?? $this->generateBillNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $bill = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $billNo) {
            $bill = Bill::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'purchase_order_id' => $formData['purchase_order_id'] ?? null,
                'bill_no' => $billNo,
                'bill_date' => $formData['bill_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $bill->billItems()->createMany($items);

            return $bill;
        });

        $bill->load([
            'party',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
        ]);

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_bill", group="bill", desc="Show Bill")
     */
    public function show(Bill $bill)
    {
        $bill->load([
            'party',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
        ]);

        return BillResource::make($bill);
    }

    /**
     * @Permissions("edit_bill", group="bill", desc="Edit Bill")
     */
    public function update(BillRequest $request, Bill $bill)
    {
        if ($bill->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved bills cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();
        $billNo = $formData['bill_no'] ?? $bill->bill_no;

        $bill = DB::transaction(function () use ($bill, $formData, $billNo) {
            $bill->update([
                'party_id' => $formData['party_id'] ?? null,
                'purchase_order_id' => $formData['purchase_order_id'] ?? $bill->purchase_order_id,
                'bill_no' => $billNo,
                'bill_date' => $formData['bill_date'],
                'due_date' => $formData['due_date'] ?? null,
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $bill->billItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'warehouse_id' => $item['warehouse_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $bill->billItems()->createMany($items);

            return $bill;
        });

        $bill->load([
            'party',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
        ]);

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_bill", group="bill", desc="Delete Bill")
     */
    public function destroy(Bill $bill)
    {
        $bill->billItems()->delete();
        $bill->delete();

        return response()->json([
            'message' => 'Bill Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_bill", group="bill", desc="Approve Bill")
     */
    public function approve(Bill $bill)
    {
        if ($bill->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => BillResource::make($bill),
                'message' => 'Bill Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        $bill->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $bill->load([
            'party',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
        ]);

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Approved Successfully',
        ]);
    }

    /**
     * @Permissions("list_due_bills", group="bill", desc="List Due Bills By Party")
     */
    public function dueBills(Request $request)
    {
        $partyId = (int) $request->get('party_id');
        if (! $partyId) {
            return response()->json([
                'message' => 'party_id is required.',
            ], 422);
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
            ->where('bills.party_id', $partyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.deleted_at')
            ->select([
                'bills.id',
                'bills.bill_no',
                'bills.bill_date',
                'bills.due_date',
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get()
            ->map(function ($row) {
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total + (float) $row->tax_total;
                $paidTotal = (float) $row->paid_total;
                $due = max($grandTotal - $paidTotal, 0);
                $row->grand_total = round($grandTotal, 2);
                $row->paid_total = round($paidTotal, 2);
                $row->due_amount = round($due, 2);

                return $row;
            })
            ->filter(fn ($row) => $row->due_amount > 0)
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }

    private function generateBillNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = Bill::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'BILL-'.($count + 1).$suffix;
    }
}
