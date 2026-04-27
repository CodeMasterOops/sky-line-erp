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
use App\Services\Purchase\PurchaseBillService;

class BillController extends Controller
{
    public function __construct(
        private readonly PurchaseBillService $purchaseBillService,
    ) {}

    /**
     * @Permissions("list_bill", group="bill", desc="List Bill")
     */
    public function index(Request $request)
    {
        $bills = Bill::filter($request->all())
            ->with(['party', 'discount', 'billItems.discount'])
            ->latest('bill_date')
            ->paginate($request->limit ?? 25);

        return BillResource::collection($bills);
    }

    /**
     * @Permissions("create_bill", group="bill", desc="Create Bill")
     */
    public function store(BillRequest $request)
    {
        $bill = $this->purchaseBillService->createBill($request->validated());

        $bill->load([
            'party',
            'discount',
            'billItems.discount',
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
            'discount',
            'billItems.discount',
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
        if ($bill->voided_at) {
            return response()->json([
                'message' => 'Voided bills cannot be edited.',
            ], 422);
        }

        if ($bill->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved bills cannot be edited.',
            ], 422);
        }

        $this->purchaseBillService->updateBill($request->validated(), $bill);

        $bill->load([
            'party',
            'discount',
            'billItems.discount',
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
        if ($bill->status === StatusEnum::APPROVED && ! $bill->voided_at) {
            return response()->json([
                'message' => __('Approved bills must be voided before they can be deleted.'),
            ], 422);
        }

        $bill->billItems()->delete();
        $bill->delete();

        return response()->json([
            'message' => 'Bill Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_bill", group="bill", desc="Void Bill")
     */
    public function void(Bill $bill)
    {
        if ($bill->voided_at) {
            return response()->json([
                'data' => BillResource::make($bill),
                'message' => 'Bill is already voided.',
            ]);
        }

        if ($bill->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved bills can be voided.',
            ], 422);
        }

        $this->purchaseBillService->voidBill($bill);

        $bill->load([
            'party',
            'discount',
            'billItems.discount',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
        ]);

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill voided successfully.',
        ]);
    }

    /**
     * @Permissions("approve_bill", group="bill", desc="Approve Bill")
     */
    public function approve(Bill $bill)
    {
        if ($bill->voided_at) {
            return response()->json([
                'message' => 'Voided bills cannot be approved.',
            ], 422);
        }

        if ($bill->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => BillResource::make($bill),
                'message' => 'Bill Already Approved',
            ]);
        }

        $this->purchaseBillService->approveBill($bill);

        $bill->load([
            'party',
            'discount',
            'billItems.discount',
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
            ->leftJoin('discounts', function ($join) {
                $join->on('bills.id', '=', 'discounts.discountable_id')
                    ->where('discounts.discountable_type', '=', \App\Models\Bill::class);
            })
            ->where('bills.party_id', $partyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->whereNull('bills.deleted_at')
            ->select([
                'bills.id',
                'bills.bill_no',
                'bills.bill_date',
                'bills.due_date',
                DB::raw('COALESCE(discounts.amount, 0) as order_discount_amount'),
                DB::raw('COALESCE(item_totals.subtotal, 0) as subtotal'),
                DB::raw('COALESCE(item_totals.discount_total, 0) as discount_total'),
                DB::raw('COALESCE(item_totals.tax_total, 0) as tax_total'),
                DB::raw('COALESCE(paid_totals.paid_total, 0) as paid_total'),
            ])
            ->get()
            ->map(function ($row) {
                $orderDisc = (float) ($row->order_discount_amount ?? 0);
                $grandTotal = (float) $row->subtotal - (float) $row->discount_total - $orderDisc + (float) $row->tax_total;
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
}
