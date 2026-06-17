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
     * @return array<int, string>
     */
    private function billRelations(): array
    {
        return [
            'party',
            'discount',
            'billItems.discount',
            'billItems.productVariant.product',
            'billItems.unit',
            'billItems.tax',
            'billItems.warehouse',
            'billItems.grnItem.goodsReceivedNote.landedCosts.account',
            'landedCosts.account',
            'landedCosts.allocations',
            'paymentAllocations.payment',
        ];
    }

    #[Permissions('list_bill', group: 'bill', desc: 'List Bill')]
    public function index(Request $request)
    {
        $bills = Bill::filter($request->all())
            ->with(['party', 'discount', 'billItems.discount', 'paymentAllocations.payment'])
            ->latest('bill_date')
            ->paginate($request->limit ?? 25);

        return BillResource::collection($bills);
    }

    #[Permissions('create_bill', group: 'bill', desc: 'Create Bill')]
    public function store(BillRequest $request)
    {
        $bill = $this->purchaseBillService->createBill($request->validated());

        $bill->load($this->billRelations());

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Added Successfully',
        ], 201);
    }

    #[Permissions('show_bill', group: 'bill', desc: 'Show Bill')]
    public function show(Bill $bill)
    {
        $bill->load($this->billRelations());

        return BillResource::make($bill);
    }

    #[Permissions('edit_bill', group: 'bill', desc: 'Edit Bill')]
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

        $bill->load($this->billRelations());

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Updated Successfully',
        ]);
    }

    #[Permissions('delete_bill', group: 'bill', desc: 'Delete Bill')]
    public function destroy(Bill $bill)
    {
        if ($bill->status === StatusEnum::APPROVED && ! $bill->voided_at) {
            return response()->json([
                'message' => __('Approved bills must be voided before they can be deleted.'),
            ], 422);
        }

        $bill->billItems()->delete();
        $bill->landedCosts()->delete();
        $bill->paymentAllocations()->delete();
        $bill->delete();

        return response()->json([
            'message' => 'Bill Deleted Successfully',
        ]);
    }

    #[Permissions('void_bill', group: 'bill', desc: 'Void Bill')]
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

        $bill->load($this->billRelations());

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill voided successfully.',
        ]);
    }

    #[Permissions('approve_bill', group: 'bill', desc: 'Approve Bill')]
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

        $bill->load($this->billRelations());

        return response()->json([
            'data' => BillResource::make($bill),
            'message' => 'Bill Approved Successfully',
        ]);
    }

    #[Permissions('list_due_bills', group: 'bill', desc: 'List Due Bills By Party')]
    public function dueBills(Request $request)
    {
        $partyId = (int) $request->get('party_id');
        if (! $partyId) {
            return response()->json([
                'message' => 'party_id is required.',
            ], 422);
        }

        $companyId = auth('admin')->user()?->company_id;
        $billMorphClass = (new Bill)->getMorphClass();
        $approved = StatusEnum::APPROVED->value;

        $billItemsAgg = \Illuminate\Support\Facades\DB::table('bill_items')
            ->selectRaw('bill_id, COALESCE(SUM(quantity * rate), 0) as subtotal, COALESCE(SUM(discount_amount), 0) as line_discount, COALESCE(SUM(tax_amount), 0) as tax')
            ->whereNull('deleted_at')
            ->groupBy('bill_id');

        $discountsAgg = \Illuminate\Support\Facades\DB::table('discounts')
            ->selectRaw('discountable_id, COALESCE(SUM(amount), 0) as amount')
            ->where('discountable_type', (new Bill)->getMorphClass())
            ->groupBy('discountable_id');

        $paAgg = \Illuminate\Support\Facades\DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->selectRaw('pa.payable_id, COALESCE(SUM(pa.amount), 0) as paid')
            ->where('pa.payable_type', $billMorphClass)
            ->where('p.status', $approved)
            ->where('p.company_id', $companyId)
            ->whereNull('pa.deleted_at')
            ->whereNull('p.deleted_at')
            ->groupBy('pa.payable_id');

        $perBill = \Illuminate\Support\Facades\DB::table('bills as b')
            ->select(['b.id', 'b.bill_no', 'b.bill_date', 'b.due_date'])
            ->selectRaw('COALESCE(bi.subtotal, 0) as subtotal')
            ->selectRaw('COALESCE(bi.line_discount, 0) as discount_total')
            ->selectRaw('COALESCE(bi.tax, 0) as tax_total')
            ->selectRaw('COALESCE(d.amount, 0) as order_discount_amount')
            ->selectRaw('COALESCE(bi.subtotal, 0) - COALESCE(bi.line_discount, 0) + COALESCE(bi.tax, 0) - COALESCE(d.amount, 0) as grand_total')
            ->selectRaw('COALESCE(pa.paid, 0) as paid_total')
            ->leftJoinSub($billItemsAgg, 'bi', 'bi.bill_id', '=', 'b.id')
            ->leftJoinSub($discountsAgg, 'd', 'd.discountable_id', '=', 'b.id')
            ->leftJoinSub($paAgg, 'pa', 'pa.payable_id', '=', 'b.id')
            ->where('b.party_id', $partyId)
            ->where('b.company_id', $companyId)
            ->where('b.status', $approved)
            ->whereNull('b.voided_at')
            ->whereNull('b.deleted_at');

        $rows = \Illuminate\Support\Facades\DB::query()
            ->fromSub($perBill, 'sub')
            ->whereRaw('grand_total > paid_total')
            ->orderByDesc('bill_date')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'bill_no' => $row->bill_no,
                'bill_date' => $row->bill_date,
                'due_date' => $row->due_date,
                'order_discount_amount' => round((float) $row->order_discount_amount, 2),
                'subtotal' => round((float) $row->subtotal, 2),
                'discount_total' => round((float) $row->discount_total, 2),
                'tax_total' => round((float) $row->tax_total, 2),
                'grand_total' => round((float) $row->grand_total, 2),
                'paid_total' => round((float) $row->paid_total, 2),
                'due_amount' => round((float) $row->grand_total - (float) $row->paid_total, 2),
            ])
            ->values();

        return response()->json([
            'data' => $rows,
        ]);
    }
}
