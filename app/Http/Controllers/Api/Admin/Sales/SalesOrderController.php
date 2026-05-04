<?php

namespace App\Http\Controllers\Api\Admin\Sales;

use App\Enums\StatusEnum;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use App\Annotation\Permissions;
use App\Http\Controllers\Controller;
use App\Services\Sales\SalesOrderService;
use App\Http\Resources\Admin\Sales\SalesOrderResource;
use App\Http\Requests\Api\Admin\Sales\SalesOrderRequest;

class SalesOrderController extends Controller
{
    public function __construct(
        private readonly SalesOrderService $salesOrderService
    ) {}

    /**
     * @Permissions("list_sales_order", group="sales_order", desc="List Sales Order")
     */
    public function index(Request $request)
    {
        $orders = SalesOrder::filter($request->all())
            ->with(['party', 'discount', 'salesOrderItems.discount'])
            ->withCount(['invoices'])
            ->latest('order_date')
            ->paginate($request->limit ?? 25);

        return SalesOrderResource::collection($orders);
    }

    /**
     * @Permissions("create_sales_order", group="sales_order", desc="Create Sales Order")
     */
    public function store(SalesOrderRequest $request)
    {
        $order = $this->salesOrderService->createSalesOrder($request->validated());

        $order->load([
            'party',
            'discount',
            'salesOrderItems.discount',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($order),
            'message' => 'Sales Order Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_sales_order", group="sales_order", desc="Show Sales Order")
     */
    public function show(SalesOrder $salesOrder)
    {
        $salesOrder->load([
            'party',
            'discount',
            'salesOrderItems.discount',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return SalesOrderResource::make($salesOrder);
    }

    /**
     * @Permissions("edit_sales_order", group="sales_order", desc="Edit Sales Order")
     */
    public function update(SalesOrderRequest $request, SalesOrder $salesOrder)
    {
        if ($salesOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved sales orders cannot be edited.',
            ], 422);
        }

        $this->salesOrderService->updateSalesOrder($request->validated(), $salesOrder);

        $salesOrder->load([
            'party',
            'discount',
            'salesOrderItems.discount',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($salesOrder),
            'message' => 'Sales Order Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_sales_order", group="sales_order", desc="Delete Sales Order")
     */
    public function destroy(SalesOrder $salesOrder)
    {
        $salesOrder->salesOrderItems()->delete();
        $salesOrder->delete();

        return response()->json([
            'message' => 'Sales Order Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_sales_order", group="sales_order", desc="Approve Sales Order")
     */
    public function approve(SalesOrder $salesOrder)
    {
        if ($salesOrder->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => SalesOrderResource::make($salesOrder),
                'message' => 'Sales Order Already Approved',
            ]);
        }

        $this->salesOrderService->approveSalesOrder($salesOrder);

        $salesOrder->load([
            'party',
            'discount',
            'salesOrderItems.discount',
            'salesOrderItems.productVariant.product',
            'salesOrderItems.unit',
            'salesOrderItems.tax',
        ]);

        return response()->json([
            'data' => SalesOrderResource::make($salesOrder),
            'message' => 'Sales Order Approved Successfully',
        ]);
    }
}
