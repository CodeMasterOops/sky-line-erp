<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\Company;
use App\Enums\StatusEnum;
use Illuminate\Http\Request;
use App\Models\StockTransfer;
use App\Models\ProductVariant;
use App\Annotation\Permissions;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Validation\ValidationException;
use App\Services\Inventory\InventoryLayerTransferService;
use App\Http\Resources\Admin\Inventory\StockTransferResource;
use App\Http\Requests\Api\Admin\Inventory\StockTransferRequest;

class StockTransferController extends Controller
{
    public function __construct(
        private InventoryLayerTransferService $inventoryTransfer,
    ) {}

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

        try {
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

                $items = $this->normalizedStockTransferItems($formData['items'] ?? []);

                $transfer->stockTransferItems()->createMany($items);

                if ($status === StatusEnum::APPROVED->value) {
                    $this->applyApprovalEffects($transfer);
                }

                return $transfer;
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $transfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItems.productVariant.product',
            'stockTransferItems.unit',
        ]);

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

            $items = $this->normalizedStockTransferItems($formData['items'] ?? []);

            $stockTransfer->stockTransferItems()->createMany($items);

            return $stockTransfer;
        });

        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItems.productVariant.product',
            'stockTransferItems.unit',
        ]);

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

        try {
            DB::transaction(function () use ($stockTransfer, $user) {
                $stockTransfer->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyApprovalEffects($stockTransfer);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItems.productVariant.product',
            'stockTransferItems.unit',
        ]);

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer),
            'message' => 'Stock Transfer Approved Successfully',
        ]);
    }

    /**
     * Quantity is always in the variant’s stock unit; unit_id is stored for display only and defaults from the product.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{product_variant_id: int, unit_id: ?int, quantity: int}>
     */
    private function normalizedStockTransferItems(array $items): array
    {
        $variantIds = collect($items)->pluck('product_variant_id')->unique()->filter()->all();
        $unitByVariantId = ProductVariant::query()
            ->whereIn('id', $variantIds)
            ->with('product:id,unit_id')
            ->get()
            ->keyBy('id')
            ->map(fn (ProductVariant $v) => $v->product?->unit_id);

        return collect($items)->map(function (array $item) use ($unitByVariantId) {
            $vid = (int) $item['product_variant_id'];
            $fromRequest = $item['unit_id'] ?? null;
            $fallback = $unitByVariantId->get($vid);
            $unitId = ($fromRequest !== null && $fromRequest !== '')
                ? (int) $fromRequest
                : ($fallback !== null && $fallback !== '' ? (int) $fallback : null);

            return [
                'product_variant_id' => $vid,
                'unit_id' => $unitId,
                'quantity' => (int) $item['quantity'],
            ];
        })->all();
    }

    private function applyApprovalEffects(StockTransfer $transfer): void
    {
        $transfer->loadMissing('stockTransferItems');
        $user = auth('admin')->user();
        $company = Company::findOrFail($transfer->company_id);

        foreach ($transfer->stockTransferItems as $item) {
            $this->inventoryTransfer->applyLine(
                $company,
                $transfer,
                $item,
                $user->id,
                $transfer->remarks,
            );
        }
    }
}
