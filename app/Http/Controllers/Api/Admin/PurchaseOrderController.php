<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Bin;
use App\Models\Bill;
use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Models\PurchaseOrder;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\BillResource;
use App\Http\Resources\Admin\PurchaseOrderResource;
use App\Http\Requests\Api\Admin\PurchaseOrderRequest;

class PurchaseOrderController extends Controller
{
    /**
     * @Permissions("list_purchase_order", group="purchase_order", desc="List Purchase Order")
     */
    public function index(Request $request)
    {
        $orders = PurchaseOrder::filter($request->all())
            ->with(['party', 'purchaseOrderItems'])
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
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $orderNo = $formData['order_no'] ?? $this->generateOrderNo($fiscalYearId, $setting->fiscalYear?->year_code);

        $order = DB::transaction(function () use ($formData, $user, $status, $fiscalYearId, $orderNo) {
            $order = PurchaseOrder::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $formData['party_id'] ?? null,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
                'create_user_id' => $user->id,
                'approve_user_id' => $status === StatusEnum::APPROVED->value ? $user->id : null,
                'approved_at' => $status === StatusEnum::APPROVED->value ? now() : null,
                'status' => $status,
            ]);

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $order->purchaseOrderItems()->createMany($items);

            return $order;
        });

        $order->load([
            'party',
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

        $formData = $request->validated();
        $orderNo = $formData['order_no'] ?? $purchaseOrder->order_no;

        $purchaseOrder = DB::transaction(function () use ($purchaseOrder, $formData, $orderNo) {
            $purchaseOrder->update([
                'party_id' => $formData['party_id'] ?? null,
                'order_no' => $orderNo,
                'order_date' => $formData['order_date'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $purchaseOrder->purchaseOrderItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'tax_id' => $item['tax_id'] ?? null,
                    'tax_amount' => $item['tax_amount'] ?? 0,
                    'discount_amount' => $item['discount_amount'] ?? 0,
                ];
            })->all();

            $purchaseOrder->purchaseOrderItems()->createMany($items);

            return $purchaseOrder;
        });

        $purchaseOrder->load([
            'party',
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

        $user = auth('admin')->user();

        $purchaseOrder->update([
            'approve_user_id' => $user->id,
            'approved_at' => now(),
            'status' => StatusEnum::APPROVED->value,
        ]);

        $purchaseOrder->load([
            'party',
            'purchaseOrderItems.productVariant.product',
            'purchaseOrderItems.unit',
            'purchaseOrderItems.tax',
        ]);

        return response()->json([
            'data' => PurchaseOrderResource::make($purchaseOrder),
            'message' => 'Purchase Order Approved Successfully',
        ]);
    }

    /**
     * @Permissions("convert_purchase_order_to_bill", group="purchase_order", desc="Convert Purchase Order To Bill")
     */
    public function convertToBill(Request $request, PurchaseOrder $purchaseOrder)
    {
        if ($purchaseOrder->status !== StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Only approved purchase orders can be converted to bill.',
            ], 422);
        }

        if ($purchaseOrder->bills()->exists()) {
            return response()->json([
                'message' => 'Purchase order already converted to bill.',
            ], 422);
        }

        $data = $request->validate([
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'due_date' => ['nullable', 'date'],
        ]);

        $purchaseOrder->loadMissing([
            'purchaseOrderItems',
            'party',
        ]);

        $user = auth('admin')->user();
        $setting = $user->company;
        $fiscalYearId = $setting->fiscal_year_id;
        $billNo = $this->generateBillNo($fiscalYearId, $setting->fiscalYear?->year_code);
        $billDate = now()->toDateString();
        $defaultBinId = Bin::defaultIdForWarehouse($setting->id, (int) $data['warehouse_id']);

        $bill = DB::transaction(function () use ($purchaseOrder, $user, $fiscalYearId, $billNo, $billDate, $data, $defaultBinId) {
            $bill = Bill::create([
                'fiscal_year_id' => $fiscalYearId,
                'party_id' => $purchaseOrder->party_id,
                'purchase_order_id' => $purchaseOrder->id,
                'bill_no' => $billNo,
                'bill_date' => $billDate,
                'due_date' => $data['due_date'] ?? null,
                'remarks' => $purchaseOrder->remarks,
                'create_user_id' => $user->id,
                'approve_user_id' => $user->id,
                'approved_at' => null,
                'status' => StatusEnum::DRAFT->value,
            ]);

            $items = $purchaseOrder->purchaseOrderItems->map(function ($item) use ($data, $defaultBinId) {
                return [
                    'product_variant_id' => $item->product_variant_id,
                    'warehouse_id' => $data['warehouse_id'],
                    'bin_id' => $defaultBinId,
                    'unit_id' => $item->unit_id,
                    'quantity' => $item->quantity,
                    'rate' => $item->rate,
                    'tax_id' => $item->tax_id,
                    'tax_amount' => $item->tax_amount,
                    'discount_amount' => $item->discount_amount,
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
            'message' => 'Bill created from purchase order successfully.',
        ]);
    }

    private function generateOrderNo(?int $fiscalYearId, ?string $yearCode): string
    {
        $count = PurchaseOrder::where('fiscal_year_id', $fiscalYearId)
            ->withTrashed()
            ->count();

        $suffix = $yearCode ? '/'.$yearCode : '';

        return 'PO-'.($count + 1).$suffix;
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
