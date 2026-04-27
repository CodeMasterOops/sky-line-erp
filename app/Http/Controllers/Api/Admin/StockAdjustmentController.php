<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Company;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Enums\ChangeTypeEnum;
use App\Annotation\Permissions;
use App\Models\StockAdjustment;
use App\Enums\StockDirectionEnum;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\Admin\StockAdjustmentResource;
use App\Services\Inventory\InventoryLayerIssueService;
use App\Http\Requests\Api\Admin\StockAdjustmentRequest;
use App\Services\Inventory\InventoryLayerReceiptService;

class StockAdjustmentController extends Controller
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private InventoryLayerIssueService $inventoryIssue,
    ) {}

    /**
     * @Permissions("list_stock_adjustment", group="stock_adjustment", desc="List Stock Adjustment")
     */
    public function index(Request $request)
    {
        $query = StockAdjustment::with(['warehouse'])
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where('reference_no', 'like', $key);
        }

        $adjustments = $query->paginate($request->limit ?? 25);

        return StockAdjustmentResource::collection($adjustments);
    }

    /**
     * @Permissions("create_stock_adjustment", group="stock_adjustment", desc="Create Stock Adjustment")
     */
    public function store(StockAdjustmentRequest $request)
    {
        $formData = $request->validated();
        $user = auth('admin')->user();
        $status = $formData['status'] ?? StatusEnum::DRAFT->value;

        try {
            $adjustment = DB::transaction(function () use ($formData, $user, $status) {
                $adjustment = StockAdjustment::create([
                    'reference_no' => $formData['reference_no'] ?? null,
                    'date' => $formData['date'],
                    'warehouse_id' => $formData['warehouse_id'],
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
                        'direction' => $item['direction'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'] ?? null,
                    ];
                })->all();

                $adjustment->stockAdjustmentItems()->createMany($items);

                if ($status === StatusEnum::APPROVED->value) {
                    $this->applyApprovalEffects($adjustment);
                }

                return $adjustment;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $adjustment->load(['warehouse', 'stockAdjustmentItems.productVariant.product', 'stockAdjustmentItems.unit']);

        return response()->json([
            'data' => StockAdjustmentResource::make($adjustment),
            'message' => 'Stock Adjustment Added Successfully',
        ], 201);
    }

    /**
     * @Permissions("show_stock_adjustment", group="stock_adjustment", desc="Show Stock Adjustment")
     */
    public function show(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->load([
            'warehouse',
            'stockAdjustmentItems.productVariant.product',
            'stockAdjustmentItems.unit',
        ]);

        return StockAdjustmentResource::make($stockAdjustment);
    }

    /**
     * @Permissions("edit_stock_adjustment", group="stock_adjustment", desc="Edit Stock Adjustment")
     */
    public function update(StockAdjustmentRequest $request, StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved stock adjustments cannot be edited.',
            ], 422);
        }

        $formData = $request->validated();

        $stockAdjustment = DB::transaction(function () use ($stockAdjustment, $formData) {
            $stockAdjustment->update([
                'reference_no' => $formData['reference_no'] ?? null,
                'date' => $formData['date'],
                'warehouse_id' => $formData['warehouse_id'],
                'remarks' => $formData['remarks'] ?? null,
            ]);

            $stockAdjustment->stockAdjustmentItems()->delete();

            $items = collect($formData['items'] ?? [])->map(function ($item) {
                return [
                    'product_variant_id' => $item['product_variant_id'],
                    'unit_id' => $item['unit_id'] ?? null,
                    'direction' => $item['direction'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? null,
                ];
            })->all();

            $stockAdjustment->stockAdjustmentItems()->createMany($items);

            return $stockAdjustment;
        });

        $stockAdjustment->load(['warehouse', 'stockAdjustmentItems.productVariant.product', 'stockAdjustmentItems.unit']);

        return response()->json([
            'data' => StockAdjustmentResource::make($stockAdjustment),
            'message' => 'Stock Adjustment Updated Successfully',
        ]);
    }

    /**
     * @Permissions("delete_stock_adjustment", group="stock_adjustment", desc="Delete Stock Adjustment")
     */
    public function destroy(StockAdjustment $stockAdjustment)
    {
        $stockAdjustment->stockAdjustmentItems()->delete();
        $stockAdjustment->delete();

        return response()->json([
            'message' => 'Stock Adjustment Deleted Successfully',
        ]);
    }

    /**
     * @Permissions("approve_stock_adjustment", group="stock_adjustment", desc="Approve Stock Adjustment")
     */
    public function approve(StockAdjustment $stockAdjustment)
    {
        if ($stockAdjustment->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => StockAdjustmentResource::make($stockAdjustment),
                'message' => 'Stock Adjustment Already Approved',
            ]);
        }

        $user = auth('admin')->user();

        try {
            DB::transaction(function () use ($stockAdjustment, $user) {
                $stockAdjustment->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyApprovalEffects($stockAdjustment);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $stockAdjustment->load(['warehouse', 'stockAdjustmentItems.productVariant.product', 'stockAdjustmentItems.unit']);

        return response()->json([
            'data' => StockAdjustmentResource::make($stockAdjustment),
            'message' => 'Stock Adjustment Approved Successfully',
        ]);
    }

    private function applyApprovalEffects(StockAdjustment $adjustment): void
    {
        $adjustment->loadMissing('stockAdjustmentItems');
        $user = auth('admin')->user();
        $company = Company::findOrFail($adjustment->company_id);

        foreach ($adjustment->stockAdjustmentItems as $item) {
            $quantity = (int) $item->quantity;
            if ($quantity <= 0) {
                continue;
            }

            if ($item->direction === StockDirectionEnum::IN->value) {
                $unitCost = (float) ($item->unit_cost ?? 0);

                $this->inventoryReceipt->receive(
                    $company,
                    $adjustment,
                    $item->product_variant_id,
                    $adjustment->warehouse_id,
                    $quantity,
                    $unitCost,
                    ChangeTypeEnum::ADJUSTMENT_IN,
                    $user->id,
                    $adjustment->remarks,
                    null,
                );
            } else {
                $this->inventoryIssue->issue(
                    $company,
                    $adjustment,
                    $item->product_variant_id,
                    $adjustment->warehouse_id,
                    $quantity,
                    ChangeTypeEnum::ADJUSTMENT_OUT,
                    $user->id,
                    $adjustment->remarks,
                );
            }
        }
    }
}
