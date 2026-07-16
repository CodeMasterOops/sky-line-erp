<?php

namespace App\Services\Inventory;

use App\Models\User;
use App\Models\Stock;
use App\Models\Company;
use App\Enums\StatusEnum;
use App\Enums\ChangeTypeEnum;
use App\Models\ProductVariant;
use App\Models\OpeningStockEntry;
use Illuminate\Support\Facades\DB;
use App\Models\OpeningStockEntryItem;
use Illuminate\Validation\ValidationException;

class OpeningStockEntryService
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private BatchResolver $batchResolver,
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
