<?php

namespace App\Http\Controllers\Api\Admin\Purchase;

use App\Models\Bill;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Purchase\PurchaseBillService;
use App\Http\Resources\Admin\Purchase\BillResource;
use App\Http\Requests\Api\Admin\Purchase\BillRequest;

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

        $rows = Bill::query()
            ->where('bills.party_id', $partyId)
            ->where('bills.status', StatusEnum::APPROVED->value)
            ->with([
                'discount:id,discountable_id,discountable_type,amount',
                'billItems:id,bill_id,quantity,rate,discount_amount,tax_amount',
                'paymentAllocations:id,payment_id,payable_id,payable_type,amount',
                'paymentAllocations.payment:id,status',
            ])
            ->latest('bill_date')
            ->get()
            ->map(function (Bill $bill) {
                $subtotal = (float) $bill->billItems->sum(fn ($item) => (float) $item->quantity * (float) $item->rate);
                $discountTotal = (float) $bill->billItems->sum('discount_amount');
                $taxTotal = (float) $bill->billItems->sum('tax_amount');
                $orderDisc = (float) ($bill->discount?->amount ?? 0);
                $grandTotal = $subtotal - $discountTotal - $orderDisc + $taxTotal;
                $paidTotal = (float) $bill->paymentAllocations
                    ->filter(fn ($allocation) => $allocation->payment?->status === StatusEnum::APPROVED)
                    ->sum('amount');
                $due = max($grandTotal - $paidTotal, 0);

                return [
                    'id' => $bill->id,
                    'bill_no' => $bill->bill_no,
                    'bill_date' => $bill->bill_date,
                    'due_date' => $bill->due_date,
                    'order_discount_amount' => round($orderDisc, 2),
                    'subtotal' => round($subtotal, 2),
                    'discount_total' => round($discountTotal, 2),
                    'tax_total' => round($taxTotal, 2),
                    'grand_total' => round($grandTotal, 2),
                    'paid_total' => round($paidTotal, 2),
                    'due_amount' => round($due, 2),
                ];
            })
            ->filter(fn (array $row) => $row['due_amount'] > 0)
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
