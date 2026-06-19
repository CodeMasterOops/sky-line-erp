<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Models\Batch;
use App\Models\Stock;
use App\Models\Company;
use App\Enums\StatusEnum;
use App\Enums\ChangeTypeEnum;
use App\Enums\BatchStatusEnum;
use App\Models\ProductVariant;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\DB;
use App\Models\OpeningStockEntryItem;
use Illuminate\Validation\ValidationException;

class OpeningStockEntryService
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
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
     * Import path: create a single-line entry and approve immediately.
     *
     * @param  array{warehouse_id: int, quantity: int}  $opening
     */
    public function applyImportOpeningStock(
        int $companyId,
        ?int $branchId,
        ProductVariant $variant,
        array $opening,
        float $unitCost,
        int $userId,
    ): void {
        if ($variant->product?->isService()) {
            return;
        }

        $quantity = (int) ($opening['quantity'] ?? 0);
        if ($quantity <= 0) {
            return;
        }

        $user = User::query()->findOrFail($userId);
        $company = Company::findOrFail($companyId);

        DB::transaction(function () use ($company, $branchId, $variant, $opening, $unitCost, $quantity, $user) {
            $entry = OpeningStockEntry::create([
                'company_id' => $company->id,
                'branch_id' => $branchId,
                'reference_no' => 'IMPORT-'.$variant->id,
                'date' => now()->toDateString(),
                'warehouse_id' => $opening['warehouse_id'],
                'remarks' => __('Product import opening stock'),
                'create_user_id' => $user->id,
                'status' => StatusEnum::DRAFT,
            ]);

            $item = $entry->openingStockEntryItems()->create([
                'product_variant_id' => $variant->id,
                'unit_id' => $variant->product?->unit_id,
                'quantity' => $quantity,
                'unit_cost' => max(0, $unitCost),
            ]);

            $this->applyItem($company, $entry, $item, $user);

            $entry->update([
                'approve_user_id' => $user->id,
                'approved_at' => now(),
                'status' => StatusEnum::APPROVED,
            ]);
        });
    }

    private function applyItem(
        Company $company,
        OpeningStockEntry $entry,
        OpeningStockEntryItem $item,
        User $user,
    ): void {
        $quantity = (int) $item->quantity;
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

        $existingQty = (int) ($existingStock?->quantity ?? 0);

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

        $batchId = $item->batch_id ?? $this->findOrCreateBatch($item, $company, (int) $entry->warehouse_id, $unitCost);

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

    private function findOrCreateBatch(OpeningStockEntryItem $item, Company $company, int $warehouseId, float $unitCost): ?int
    {
        if (empty($item->batch_no)) {
            return null;
        }

        $batch = Batch::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $item->product_variant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('batch_no', $item->batch_no)
            ->first();

        if (! $batch) {
            $batch = Batch::create([
                'company_id' => $company->id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $warehouseId,
                'batch_no' => $item->batch_no,
                'lot_no' => $item->batch_no,
                'expiry_date' => $item->expiry_date,
                'initial_qty' => 0,
                'remaining_qty' => 0,
                'unit_cost' => $unitCost,
                'status' => BatchStatusEnum::Active,
            ]);
        }

        $item->update(['batch_id' => $batch->id]);

        return $batch->id;
    }
}
