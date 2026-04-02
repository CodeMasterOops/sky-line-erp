<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Stock;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Models\StockTransfer;
use App\Annotation\Permissions;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\StockTransferResource;
use App\Http\Requests\Api\Admin\StockTransferRequest;

class StockTransferController extends Controller
{
    /**
     * @Permissions("list_stock_transfer", group="stock_transfer", desc="List Stock Transfer")
     */
    public function index(Request $request)
    {
        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse'])
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where('reference_no', 'like', $key);
        }

        $transfers = $query->paginate($request->limit ?? 25);

        return StockTransferResource::collection($transfers);
    }

    /**
     * @Permissions("create_stock_transfer", group="stock_transfer", desc="Create Stock Transfer")
     */
    public function store(StockTransferRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;

        $transfer = DB::transaction(function () use ($formData, $user, $status) {
            $transfer = StockTransfer::create([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'from_warehouse_id' => $formData['from_warehouse_id'],
                'to_warehouse_id' => $formData['to_warehouse_id'],
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
                ];
            })->all();

            $transfer->stockTransferItems()->createMany($items);

            if ($status === StatusEnum::APPROVED->value) {
                $this->applyApprovalEffects($transfer);
            }

            return $transfer;
        });

        $transfer->load(['fromWarehouse', 'toWarehouse', 'stockTransferItems.productVariant.product', 'stockTransferItems.unit']);

        return response()->json([
            'data' => StockTransferResource::make($transfer),
            'message' => 'Stock Transfer Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_stock_transfer", group="stock_transfer", desc="Show Stock Transfer")
     */
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItems.productVariant.product',
            'stockTransferItems.unit',
        ]);

        return StockTransferResource::make($stockTransfer);
    }

    /**
     * @Permissions("edit_stock_transfer", group="stock_transfer", desc="Edit Stock Transfer")
     */
    public function update(StockTransferRequest $request, StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved stock transfers cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();

        $stockTransfer = DB::transaction(function () use ($stockTransfer, $formData) {
            $stockTransfer->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'from_warehouse_id' => $formData['from_warehouse_id'],
                'to_warehouse_id' => $formData['to_warehouse_id'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $stockTransfer->stockTransferItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'quantity' => $item['quantity'],
                ];
            })->all();

            $stockTransfer->stockTransferItems()->createMany($items);

            return $stockTransfer;
        });

        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'stockTransferItems.productVariant.product', 'stockTransferItems.unit']);

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer),
            'message' => 'Stock Transfer Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_stock_transfer", group="stock_transfer", desc="Delete Stock Transfer")
     */
    public function destroy(StockTransfer $stockTransfer)
    {
        $stockTransfer->stockTransferItems()->delete();
        $stockTransfer->delete();

        return response()->json([
            'message' => 'Stock Transfer Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_stock_transfer", group="stock_transfer", desc="Approve Stock Transfer")
     */
    public function approve(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => StockTransferResource::make($stockTransfer),
                'message' => 'Stock Transfer Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        DB::transaction(function () use ($stockTransfer, $user) {
            $stockTransfer->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED->value,
            ]);

            $this->applyApprovalEffects($stockTransfer);
        });

        $stockTransfer->load(['fromWarehouse', 'toWarehouse', 'stockTransferItems.productVariant.product', 'stockTransferItems.unit']);

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer),
            'message' => 'Stock Transfer Approved Successfully',
        ]);
    }

    private function applyApprovalEffects(StockTransfer $transfer): void
    {
        $transfer->loadMissing('stockTransferItems');
        $user = auth('admin')->user();

        foreach ($transfer->stockTransferItems as $item) {
            $quantity = (int) $item->quantity;

            $this->adjustStock($item->product_variant_id, $transfer->from_warehouse_id, -$quantity);
            $this->adjustStock($item->product_variant_id, $transfer->to_warehouse_id, $quantity);

            $transfer->stockMovements()->create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $transfer->from_warehouse_id,
                'type' => ChangeTypeEnum::TRANSFER_OUT->value,
                'direction' => StockDirectionEnum::OUT->value,
                'quantity' => $quantity,
                'user_id' => $user->id,
                'remarks' => $transfer->remarks,
            ]);

            $transfer->stockMovements()->create([
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $transfer->to_warehouse_id,
                'type' => ChangeTypeEnum::TRANSFER_IN->value,
                'direction' => StockDirectionEnum::IN->value,
                'quantity' => $quantity,
                'user_id' => $user->id,
                'remarks' => $transfer->remarks,
            ]);
        }
    }

    private function adjustStock(int $productVariantId, int $warehouseId, int $delta): void
    {
        $stock = Stock::withTrashed()
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock) {
            if ($stock->trashed()) {
                $stock->restore();
            }
            $stock->quantity = (int) $stock->quantity + $delta;
            $stock->save();

            return;
        }

        Stock::create([
            'product_variant_id' => $productVariantId,
            'warehouse_id' => $warehouseId,
            'quantity' => $delta,
            'on_hold' => 0,
        ]);
    }
}
