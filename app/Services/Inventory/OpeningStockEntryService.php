<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Company;
use App\Models\Journal;
use App\Enums\StatusEnum;
use App\Models\StockLayer;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Models\ProductVariant;
use App\Enums\StockDirectionEnum;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\DB;
use App\Models\OpeningStockEntryItem;
use Illuminate\Validation\ValidationException;
use App\Services\Accounting\BooksHealthService;

class OpeningStockEntryService
{
    private const QTY_EPSILON = 0.00001;

    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private BatchResolver $batchResolver,
        private StockQuantityService $quantities,
        private BooksHealthService $booksHealth,
    ) {}

    public function approve(OpeningStockEntry $entry, User $user): void
    {
        if ($entry->status === StatusEnum::APPROVED) {
            return;
        }

        $entry->loadMissing(['openingStockEntryItems.productVariant.product']);
        $company = Company::findOrFail($entry->company_id);

        DB::transaction(function () use ($entry, $user, $company) {
            foreach ($entry->openingStockEntryItems as $item) {
                $this->applyItem($company, $entry, $item, $user);
            }

            $entry->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);
        });
    }

    /**
     * Undo an approved opening stock entry so it can be edited again, but only
     * while its opening stock is still fully intact. If any of the opened stock
     * has since been consumed by a later movement (sale, transfer, adjustment,
     * or an additional receipt into the same variant/warehouse), the reversal is
     * refused and the entry stays approved.
     */
    public function reverseApprovedOpeningStock(OpeningStockEntry $entry, User $user): void
    {
        if ($entry->status !== StatusEnum::APPROVED) {
            return;
        }

        $company = Company::findOrFail($entry->company_id);

        DB::transaction(function () use ($entry, $company): void {
            $movements = StockMovement::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('reference_type', $entry->getMorphClass())
                ->where('reference_id', $entry->id)
                ->where('type', ChangeTypeEnum::OPENING_STOCK)
                ->where('direction', StockDirectionEnum::IN)
                ->lockForUpdate()
                ->get();

            $movementIds = $movements->pluck('id')->all();
            $touchedBatches = [];

            foreach ($movements as $movement) {
                $variantId = (int) $movement->product_variant_id;
                $warehouseId = (int) $movement->warehouse_id;
                $openedQty = (float) $movement->quantity;

                $this->assertOpeningStockUntouched($company->id, $variantId, $warehouseId, $openedQty, $movementIds);

                $layers = StockLayer::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->where('product_variant_id', $variantId)
                    ->where('warehouse_id', $warehouseId)
                    ->lockForUpdate()
                    ->get();

                foreach ($layers as $layer) {
                    if ($layer->batch_id !== null) {
                        Batch::where('id', $layer->batch_id)->decrement('initial_qty', (float) $layer->qty_remaining);
                        $touchedBatches[$layer->batch_id] = true;
                    }
                    $layer->qty_remaining = 0;
                    $layer->save();
                    $layer->delete();
                }

                $this->quantities->adjust($company->id, $variantId, $warehouseId, -$openedQty);

                $this->voidMovementJournal($movement);
                $movement->delete();
            }

            foreach (array_keys($touchedBatches) as $batchId) {
                Batch::reconcileRemaining($batchId);
            }

            $this->booksHealth->invalidateCache($company->id);

            $entry->update([
                'status' => StatusEnum::DRAFT,
                'approve_user_id' => null,
                'approved_at' => null,
            ]);
        });
    }

    /**
     * @param  list<int>  $openingMovementIds
     */
    private function assertOpeningStockUntouched(int $companyId, int $variantId, int $warehouseId, float $openedQty, array $openingMovementIds): void
    {
        $hasOtherMovements = StockMovement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->whereNotIn('id', $openingMovementIds)
            ->exists();

        $stock = Stock::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->first(['quantity', 'on_hold']);

        $onHand = (float) ($stock?->quantity ?? 0);
        $onHold = (float) ($stock?->on_hold ?? 0);

        $layerRemaining = (float) StockLayer::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('product_variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->sum('qty_remaining');

        $moved = $hasOtherMovements
            || $onHold > self::QTY_EPSILON
            || abs($onHand - $openedQty) > self::QTY_EPSILON
            || abs($layerRemaining - $openedQty) > self::QTY_EPSILON;

        if ($moved) {
            throw ValidationException::withMessages([
                'items' => [
                    __('This opening stock has already been used (sold, transferred, or adjusted) and can no longer be edited. Create a Stock Adjustment instead.'),
                ],
            ]);
        }
    }

    /**
     * Void the auto-posted inventory journal for an opening movement. Uses the
     * audit-preserving soft-delete path, which the Journal model permits even in
     * a locked period (only force-deletes are blocked there).
     */
    private function voidMovementJournal(StockMovement $movement): void
    {
        if ($movement->gl_journal_id === null) {
            return;
        }

        $journal = Journal::withoutGlobalScopes()
            ->whereKey($movement->gl_journal_id)
            ->first();

        $journal?->delete();
    }

    /**
     * Dedicated Opening Stock import path: append a line to the shared per-warehouse
     * entry for this import batch and post it immediately. All lines from a single
     * import into the same warehouse consolidate under one reference.
     *
     * @return array{action: string, entry_id: int, item_id: int}
     */
    public function applyImportLine(
        int $companyId,
        ?int $branchId,
        string $referenceNo,
        int $productVariantId,
        ?int $unitId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        int $userId,
        ?string $batchNo = null,
        ?string $expiryDate = null,
        ?string $remarks = null,
    ): array {
        if ($quantity <= 0) {
            return ['action' => 'skipped', 'entry_id' => 0, 'item_id' => 0];
        }

        $user = User::query()->findOrFail($userId);
        $company = Company::findOrFail($companyId);

        return DB::transaction(function () use ($company, $branchId, $referenceNo, $productVariantId, $unitId, $warehouseId, $quantity, $unitCost, $user, $batchNo, $expiryDate, $remarks) {
            $entry = OpeningStockEntry::query()
                ->where('company_id', $company->id)
                ->where('warehouse_id', $warehouseId)
                ->where('reference_no', $referenceNo)
                ->first();

            if (! $entry) {
                $entry = OpeningStockEntry::create([
                    'company_id' => $company->id,
                    'branch_id' => $branchId,
                    'reference_no' => $referenceNo,
                    'date' => now()->toDateString(),
                    'warehouse_id' => $warehouseId,
                    'remarks' => $remarks ?? __('Opening stock import'),
                    'create_user_id' => $user->id,
                    'approve_user_id' => $user->id,
                    'approved_at' => now(),
                    'status' => StatusEnum::APPROVED,
                ]);
            }

            $item = $entry->openingStockEntryItems()->create([
                'product_variant_id' => $productVariantId,
                'unit_id' => $unitId,
                'quantity' => $quantity,
                'unit_cost' => max(0, $unitCost),
                'batch_no' => $batchNo,
                'expiry_date' => $expiryDate,
            ]);

            $this->applyItem($company, $entry, $item, $user);

            return ['action' => 'imported', 'entry_id' => $entry->id, 'item_id' => $item->id];
        });
    }

    private function applyItem(
        Company $company,
        OpeningStockEntry $entry,
        OpeningStockEntryItem $item,
        User $user,
    ): void {
        $quantity = (float) $item->quantity;
        if ($quantity <= 0) {
            return;
        }

        $variant = $item->productVariant ?? ProductVariant::query()
            ->with('product')
            ->find($item->product_variant_id);

        if ($variant?->product?->isService()) {
            return;
        }

        $existingStock = Stock::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('warehouse_id', $entry->warehouse_id)
            ->lockForUpdate()
            ->first(['quantity']);

        $existingQty = (float) ($existingStock?->quantity ?? 0);

        if ($existingQty > 0) {
            throw ValidationException::withMessages([
                'items' => [
                    __('Opening stock is for initial setup only. Variant :sku already has stock in this warehouse; use Stock Adjustment instead.', [
                        'sku' => $variant?->sku ?? (string) $item->product_variant_id,
                    ]),
                ],
            ]);
        }

        $unitCost = max(0, (float) $item->unit_cost);

        $batchId = $item->batch_id ?? $this->batchResolver->resolve($item, (int) $company->id, (int) $entry->warehouse_id, $unitCost);

        $this->inventoryReceipt->receive(
            $company,
            $entry,
            $item->product_variant_id,
            $entry->warehouse_id,
            $quantity,
            $unitCost,
            ChangeTypeEnum::OPENING_STOCK,
            $user->id,
            $entry->remarks,
            batchId: $batchId,
        );
    }
}
