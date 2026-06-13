<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Models\AdvanceReceipt;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Sales\AdvanceReceiptService;
use App\Http\Resources\Admin\Sales\AdvanceReceiptResource;
use App\Http\Requests\Api\Admin\Sales\AdvanceReceiptRequest;
use App\Http\Requests\Api\Admin\Sales\AdvanceAdjustmentRequest;

class AdvanceReceiptController extends Controller
{
    public function __construct(
        private readonly AdvanceReceiptService $advanceReceiptService,
    ) {}

    /**
     * @Permissions("list_advance_receipt", group="advance_receipt", desc="List Advance Receipts")
     */
    public function index(Request $request)
    {
        $advances = AdvanceReceipt::filter($request->all())
            ->with(['party', 'account'])
            ->latest('advance_date')
            ->paginate($request->limit ?? 25);

        return AdvanceReceiptResource::collection($advances);
    }

    /**
     * @Permissions("create_advance_receipt", group="advance_receipt", desc="Create Advance Receipt")
     */
    public function store(AdvanceReceiptRequest $request)
    {
        $advance = $this->advanceReceiptService->createAdvance($request->validated());

        $advance->load(['party', 'account']);

        return response()->json([
            'data' => AdvanceReceiptResource::make($advance),
            'message' => 'Advance Receipt Created Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_advance_receipt", group="advance_receipt", desc="Show Advance Receipt")
     */
    public function show(AdvanceReceipt $advanceReceipt)
    {
        $advanceReceipt->load(['party', 'account', 'adjustments.invoice']);

        return AdvanceReceiptResource::make($advanceReceipt);
    }

    /**
     * @Permissions("edit_advance_receipt", group="advance_receipt", desc="Edit Advance Receipt")
     */
    public function update(AdvanceReceiptRequest $request, AdvanceReceipt $advanceReceipt)
    {
        $this->advanceReceiptService->updateAdvance($request->validated(), $advanceReceipt);

        $advanceReceipt->load(['party', 'account']);

        return response()->json([
            'data' => AdvanceReceiptResource::make($advanceReceipt),
            'message' => 'Advance Receipt Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_advance_receipt", group="advance_receipt", desc="Delete Advance Receipt")
     */
    public function destroy(AdvanceReceipt $advanceReceipt)
    {
        if ($advanceReceipt->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved advances cannot be deleted. Void the advance to reverse GL entries.',
            ], 422);
        }

        $advanceReceipt->delete();

        return response()->json([
            'message' => 'Advance Receipt Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_advance_receipt", group="advance_receipt", desc="Approve Advance Receipt")
     */
    public function approve(AdvanceReceipt $advanceReceipt)
    {
        if ($advanceReceipt->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => AdvanceReceiptResource::make($advanceReceipt),
                'message' => 'Advance Receipt Already Approved',
            ]);
        }

        $this->advanceReceiptService->approveAdvance($advanceReceipt);

        $advanceReceipt->load(['party', 'account', 'adjustments.invoice']);

        return response()->json([
            'data' => AdvanceReceiptResource::make($advanceReceipt),
            'message' => 'Advance Receipt Approved Successfully',
        ]);
    }

    /**
     * @Permissions("adjust_advance_receipt", group="advance_receipt", desc="Adjust Advance Against Invoice")
     */
    public function adjust(AdvanceAdjustmentRequest $request, AdvanceReceipt $advanceReceipt)
    {
        $this->advanceReceiptService->adjustToInvoice($request->validated(), $advanceReceipt);

        $advanceReceipt->load(['party', 'account', 'adjustments.invoice']);
        $advanceReceipt->refresh();

        return response()->json([
            'data' => AdvanceReceiptResource::make($advanceReceipt),
            'message' => 'Advance adjusted against invoice successfully.',
        ]);
    }

    /**
     * @Permissions("void_advance_receipt", group="advance_receipt", desc="Void Advance Receipt")
     */
    public function void(AdvanceReceipt $advanceReceipt)
    {
        $this->advanceReceiptService->voidAdvance($advanceReceipt);

        return response()->json([
            'message' => 'Advance Receipt voided successfully.',
        ]);
    }

    /**
     * @Permissions("list_advance_receipt", group="advance_receipt", desc="List Advance Receipts")
     */
    public function partyBalance(Request $request)
    {
        $partyId = $request->party_id;

        if (! $partyId) {
            return response()->json(['balance' => 0, 'advances' => []]);
        }

        $advances = AdvanceReceipt::where('party_id', $partyId)
            ->where('status', StatusEnum::APPROVED->value)
            ->whereRaw('amount > adjusted_amount')
            ->with('account:id,name')
            ->get(['id', 'advance_no', 'advance_date', 'amount', 'adjusted_amount', 'account_id']);

        $totalBalance = $advances->sum(fn ($a) => round((float) $a->amount - (float) $a->adjusted_amount, 2));

        return response()->json([
            'balance' => round($totalBalance, 2),
            'advances' => $advances->map(fn ($a) => [
                'id' => $a->id,
                'advance_no' => $a->advance_no,
                'advance_date' => $a->advance_date?->toDateString(),
                'balance' => round((float) $a->amount - (float) $a->adjusted_amount, 2),
            ]),
        ]);
    }
}
