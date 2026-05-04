<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Models\Receipt;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Sales\ReceiptService;
use App\Http\Resources\Admin\Sales\ReceiptResource;
use App\Http\Requests\Api\Admin\Sales\ReceiptRequest;

class ReceiptController extends Controller
{
    public function __construct(
        private readonly ReceiptService $receiptService,
    ) {}

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
        $receipt = $this->receiptService->createReceipt($request->validated());

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

        $this->receiptService->updateReceipt($request->validated(), $receipt);

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

        $this->receiptService->approveReceipt($receipt);

        $receipt->load(['party', 'account', 'allocations.invoice']);

        return response()->json([
            'data' => ReceiptResource::make($receipt),
            'message' => 'Receipt Approved Successfully',
        ]);
    }
}
