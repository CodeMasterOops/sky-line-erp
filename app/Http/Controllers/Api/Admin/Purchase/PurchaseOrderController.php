<?php

namespace App\Http\Controllers\Api\Admin\Purchase;

use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Purchase\PurchaseOrderService;
use App\Http\Resources\Admin\Purchase\PurchaseOrderResource;
use App\Http\Requests\Api\Admin\Purchase\PurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $purchaseOrderService
    ) {}

    /**
     * @Permissions("list_purchase_order", group="purchase_order", desc="List Purchase Order")
     */
    public function index(Request $request)
    {
        $orders = PurchaseOrder::filter($request->all())
            ->with(['party', 'discount', 'purchaseOrderItems.discount'])
            ->withCount(['bills'])
            ->latest('order_date')
            ->paginate($request->limit ?? 25);

        return PurchaseOrderResource::collection($orders);
    }

    /**
     * @Permissions("create_purchase_order", group="purchase_order", desc="Create Purchase Order")
     */
    public function store(PurchaseOrderRequest $request)
    {
        $order = $this->purchaseOrderService->createPurchaseOrder($request->validated());

        $order->load([
            'party',
            'discount',
            'purchaseOrderItems.discount',
            'purchaseOrderItems.productVariant.product',
            'purchaseOrderItems.unit',
            'purchaseOrderItems.tax',
        ]);

        return response()->json([
            'data' => PurchaseOrderResource::make($order),
            'message' => 'Purchase Order Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_purchase_order", group="purchase_order", desc="Show Purchase Order")
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->load([
            'party',
            'discount',
            'purchaseOrderItems.discount',
            'purchaseOrderItems.productVariant.product',
            'purchaseOrderItems.unit',
            'purchaseOrderItems.tax',
        ]);

        return PurchaseOrderResource::make($purchaseOrder);
    }

    /**
     * @Permissions("edit_purchase_order", group="purchase_order", desc="Edit Purchase Order")
     */
    public function update(PurchaseOrderRequest $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved purchase orders cannot be edited.',
            ], 422);
        }

        $this->purchaseOrderService->updatePurchaseOrder($request->validated(), $purchaseOrder);

        $purchaseOrder->load([
            'party',
            'discount',
            'purchaseOrderItems.discount',
            'purchaseOrderItems.productVariant.product',
            'purchaseOrderItems.unit',
            'purchaseOrderItems.tax',
        ]);

        return response()->json([
            'data' => PurchaseOrderResource::make($purchaseOrder),
            'message' => 'Purchase Order Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_purchase_order", group="purchase_order", desc="Delete Purchase Order")
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        $purchaseOrder->purchaseOrderItems()->delete();
        $purchaseOrder->delete();

        return response()->json([
            'message' => 'Purchase Order Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_purchase_order", group="purchase_order", desc="Approve Purchase Order")
     */
    public function approve(PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => PurchaseOrderResource::make($purchaseOrder),
                'message' => 'Purchase Order Already Approved',
            ]);
        }

        $this->purchaseOrderService->approvePurchaseOrder($purchaseOrder);

        $purchaseOrder->load([
            'party',
            'discount',
            'purchaseOrderItems.discount',
            'purchaseOrderItems.productVariant.product',
            'purchaseOrderItems.unit',
            'purchaseOrderItems.tax',
        ]);

        return response()->json([
            'data' => PurchaseOrderResource::make($purchaseOrder),
            'message' => 'Purchase Order Approved Successfully',
        ]);
    }
}
