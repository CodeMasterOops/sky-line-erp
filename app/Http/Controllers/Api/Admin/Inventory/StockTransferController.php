<?php

namespace App\Http\Controllers\Api\Admin\Inventory;

use App\Models\User;
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

    #[Permissions('list_stock_transfer', group: 'stock_transfer', desc: 'List Stock Transfer')]
    public function index(Request $request)
    {
        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse'])
            ->orderByDesc('date');

        if (! empty($request->search)) {
            $key = '%'.trim($request->search).'%';
            $query->where('reference_no', 'like', $key);
        }

        if (! empty($request->status)) {
            $query->where('status', $request->status);
        }

        $transfers = $query->paginate($request->limit ?? 25);

        return StockTransferResource::collection($transfers);
    }

    #[Permissions('create_stock_transfer', group: 'stock_transfer', desc: 'Create Stock Transfer')]
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
                    'from_warehouse_id' => $formData['from_warehouse_id'] ?? null,
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
                    $this->applyApprovalEffects($transfer, $user);
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
            'stockTransferItems.fromWarehouse',
            'stockTransferItems.batch',
        ]);

        return response()->json([
            'data' => StockTransferResource::make($transfer),
            'message' => 'Stock Transfer Added Successfully',
        ], 201);
    }

    #[Permissions('show_stock_transfer', group: 'stock_transfer', desc: 'Show Stock Transfer')]
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load([
            'fromWarehouse',
            'toWarehouse',
            'stockTransferItems.productVariant.product',
            'stockTransferItems.unit',
            'stockTransferItems.fromWarehouse',
            'stockTransferItems.batch',
        ]);

        return StockTransferResource::make($stockTransfer);
    }

    #[Permissions('edit_stock_transfer', group: 'stock_transfer', desc: 'Edit Stock Transfer')]
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
                'from_warehouse_id' => $formData['from_warehouse_id'] ?? null,
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
            'stockTransferItems.fromWarehouse',
            'stockTransferItems.batch',
        ]);

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer),
            'message' => 'Stock Transfer Updated Successfully',
        ]);
    }

    #[Permissions('delete_stock_transfer', group: 'stock_transfer', desc: 'Delete Stock Transfer')]
    public function destroy(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === StatusEnum::APPROVED) {
            return response()->json([
                'message' => 'Approved stock transfers cannot be deleted. Please create a reverse transfer to correct the stock movement.',
            ], 422);
        }

        DB::transaction(function () use ($stockTransfer) {
            $stockTransfer->stockTransferItems()->delete();
            $stockTransfer->delete();
        });

        return response()->json([
            'message' => 'Stock Transfer Deleted Successfully',
        ]);
    }

    #[Permissions('dispatch_stock_transfer', group: 'stock_transfer', desc: 'Dispatch Stock Transfer (In-Transit)')]
    public function dispatch(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== StatusEnum::DRAFT) {
            return response()->json(['message' => 'Only draft transfers can be dispatched.'], 422);
        }

        $user = auth('admin')->user();
        $this->assertCanAccessTransferBranches($stockTransfer);

        try {
            DB::transaction(function () use ($stockTransfer, $user) {
                $stockTransfer->update([
                    'status' => StatusEnum::IN_TRANSIT->value,
                    'dispatch_user_id' => $user->id,
                    'dispatched_at' => now(),
                ]);

                $stockTransfer->loadMissing('stockTransferItems');
                $company = Company::findOrFail($stockTransfer->company_id);

                foreach ($stockTransfer->stockTransferItems as $item) {
                    $this->inventoryTransfer->applyDispatch(
                        $company, $stockTransfer, $item, $user->id, $stockTransfer->remarks,
                    );
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer->fresh()),
            'message' => 'Stock Transfer dispatched — now in transit.',
        ]);
    }

    #[Permissions('receive_stock_transfer', group: 'stock_transfer', desc: 'Receive Stock Transfer (In-Transit)')]
    public function receive(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status !== StatusEnum::IN_TRANSIT) {
            return response()->json(['message' => 'Only in-transit transfers can be received.'], 422);
        }

        $user = auth('admin')->user();
        $this->assertCanAccessTransferBranches($stockTransfer);

        try {
            DB::transaction(function () use ($stockTransfer, $user) {
                $stockTransfer->update([
                    'status' => StatusEnum::APPROVED->value,
                    'receive_user_id' => $user->id,
                    'received_at' => now(),
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                ]);

                $stockTransfer->loadMissing('stockTransferItems');
                $company = Company::findOrFail($stockTransfer->company_id);

                foreach ($stockTransfer->stockTransferItems as $item) {
                    $this->inventoryTransfer->applyReceive(
                        $company, $stockTransfer, $item, $user->id, $stockTransfer->remarks,
                    );
                }
            });
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'data' => StockTransferResource::make($stockTransfer->fresh()),
            'message' => 'Stock Transfer received and approved.',
        ]);
    }

    #[Permissions('approve_stock_transfer', group: 'stock_transfer', desc: 'Approve Stock Transfer')]
    public function approve(StockTransfer $stockTransfer)
    {
        if ($stockTransfer->status === StatusEnum::APPROVED) {
            return response()->json([
                'data' => StockTransferResource::make($stockTransfer),
                'message' => 'Stock Transfer Already Approved',
            ]);
        }

        $user = auth('admin')->user();
        $this->assertCanAccessTransferBranches($stockTransfer);

        try {
            DB::transaction(function () use ($stockTransfer, $user) {
                $stockTransfer->update([
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED->value,
                ]);

                $this->applyApprovalEffects($stockTransfer, $user);
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
            'stockTransferItems.fromWarehouse',
            'stockTransferItems.batch',
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
                'from_warehouse_id' => isset($item['from_warehouse_id']) ? (int) $item['from_warehouse_id'] : null,
                'quantity' => (float) $item['quantity'],
                'batch_id' => ! empty($item['batch_id']) ? (int) $item['batch_id'] : null,
            ];
        })->all();
    }

    /**
     * Dual-branch authorization for the sanctioned cross-branch transfer flow:
     * the user must be able to access every branch whose warehouse takes part
     * (source and destination). Runs on dispatch/receive/approve, which act on
     * an existing transfer without re-validating its warehouses.
     */
    private function assertCanAccessTransferBranches(StockTransfer $transfer): void
    {
        $user = auth('admin')->user();
        $transfer->loadMissing('stockTransferItems');

        $warehouseIds = collect([$transfer->from_warehouse_id, $transfer->to_warehouse_id])
            ->merge($transfer->stockTransferItems->pluck('from_warehouse_id'))
            ->filter()
            ->unique();

        foreach ($warehouseIds as $warehouseId) {
            $branchId = warehouseBranchId((int) $warehouseId);

            if ($branchId !== null && ! $user->canAccessBranch($branchId)) {
                abort(403, 'You do not have access to a branch involved in this transfer.');
            }
        }
    }

    private function applyApprovalEffects(StockTransfer $transfer, User $user): void
    {
        $transfer->loadMissing('stockTransferItems');
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
