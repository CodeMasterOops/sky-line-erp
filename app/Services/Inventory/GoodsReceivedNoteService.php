<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\GrnItem;
use App\Enums\StatusEnum;
use App\Enums\ChangeTypeEnum;
use App\Models\GoodsReceivedNote;
use App\Models\PurchaseOrderItem;
use Illuminate\Support\Facades\DB;
use App\Enums\GrnBillingStatusEnum;
use App\Services\DocumentNumberGenerator;
use Illuminate\Validation\ValidationException;

class GoodsReceivedNoteService
{
    public function __construct(
        private InventoryLayerReceiptService $inventoryReceipt,
        private InventoryDocumentReversalService $documentReversal,
        private LandedCostService $landedCosts,
        private DocumentNumberGenerator $documentNumberGenerator,
        private BatchResolver $batchResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createGrn(array $validated, Company $company, int $userId, ?string $grnNo = null): GoodsReceivedNote
    {
        return DB::transaction(function () use ($validated, $company, $userId, $grnNo) {
            $this->validatePurchaseOrderQuantities($validated);

            // See InvoiceService for the lock-inside-transaction concurrency note.
            $grnNo = $grnNo ?? $this->documentNumberGenerator->companyPadded(
                GoodsReceivedNote::class,
                'GRN-',
                $company->id,
            );

            $grn = GoodsReceivedNote::create([
                'company_id' => $company->id,
                'fiscal_year_id' => $company->fiscal_year_id,
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'party_id' => $validated['party_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'grn_no' => $grnNo,
                'received_date' => $validated['received_date'],
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'status' => StatusEnum::DRAFT->value,
                'billing_status' => GrnBillingStatusEnum::OPEN->value,
                'create_user_id' => $userId,
            ]);

            $this->syncGrnItems($grn, $validated['items'] ?? []);
            $this->landedCosts->sync($grn, $validated['landed_costs'] ?? []);

            return $grn;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateGrn(GoodsReceivedNote $grn, array $validated): GoodsReceivedNote
    {
        if ($grn->status === StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'grn' => __('Approved GRN cannot be edited.'),
            ]);
        }

        return DB::transaction(function () use ($grn, $validated) {
            $this->validatePurchaseOrderQuantities($validated, $grn->id);

            $grn->update([
                'party_id' => $validated['party_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'received_date' => $validated['received_date'],
                'supplier_invoice_no' => $validated['supplier_invoice_no'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            $grn->grnItems()->delete();
            $grn->landedCosts()->delete();
            $this->syncGrnItems($grn, $validated['items'] ?? []);
            $this->landedCosts->sync($grn, $validated['landed_costs'] ?? []);

            return $grn->fresh();
        });
    }

    public function approveGrn(GoodsReceivedNote $grn, Company $company, int $userId): GoodsReceivedNote
    {
        if ($grn->status === StatusEnum::APPROVED) {
            throw ValidationException::withMessages([
                'grn' => __('GRN is already approved.'),
            ]);
        }

        return DB::transaction(function () use ($grn, $company, $userId) {
            $grn->loadMissing('grnItems.productVariant.product');

            $grn->update([
                'status' => StatusEnum::APPROVED->value,
                'approve_user_id' => $userId,
                'approved_at' => now(),
            ]);

            $this->applyInventoryReceipts($grn->fresh(), $company, $userId);
            $this->landedCosts->applyApprovedGrnCosts($grn->fresh(['grnItems.productVariant.product', 'landedCosts']), $company, $userId);

            return $grn->fresh();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function billableItemsForParty(int $partyId, ?int $warehouseId = null): array
    {
        $query = GrnItem::query()
            ->with([
                'productVariant.product',
                'unit',
                'goodsReceivedNote:id,grn_no,party_id,warehouse_id,received_date,status,billing_status',
                'goodsReceivedNote.landedCosts.account',
            ])
            ->whereHas('goodsReceivedNote', function ($q) use ($partyId, $warehouseId) {
                $q->where('party_id', $partyId)
                    ->where('status', StatusEnum::APPROVED->value)
                    ->whereIn('billing_status', [
                        GrnBillingStatusEnum::OPEN->value,
                        GrnBillingStatusEnum::PARTIALLY_BILLED->value,
                    ]);

                if ($warehouseId) {
                    $q->where('warehouse_id', $warehouseId);
                }
            });

        $items = [];

        foreach ($query->get() as $grnItem) {
            $grnItem->loadMissing('productVariant.product');
            if ($grnItem->productVariant?->isService()) {
                continue;
            }

            $receivedQty = (float) $grnItem->received_qty;
            $billedQty = (float) $grnItem->billed_qty;
            $remainingQty = max(0, $receivedQty - $billedQty);

            if ($remainingQty <= 0) {
                continue;
            }

            $items[] = [
                'grn_item_id' => $grnItem->id,
                'goods_received_note_id' => $grnItem->goods_received_note_id,
                'grn_no' => $grnItem->goodsReceivedNote?->grn_no,
                'received_date' => $grnItem->goodsReceivedNote?->received_date,
                'warehouse_id' => $grnItem->goodsReceivedNote?->warehouse_id,
                'product_variant_id' => $grnItem->product_variant_id,
                'product_variant' => $grnItem->productVariant,
                'unit_id' => $grnItem->unit_id,
                'received_qty' => $receivedQty,
                'billed_qty' => $billedQty,
                'remaining_qty' => $remainingQty,
                'unit_cost' => (float) $grnItem->unit_cost,
                'purchase_order_item_id' => $grnItem->purchase_order_item_id,
                'grn_landed_costs' => $grnItem->goodsReceivedNote?->landedCosts
                    ?->map(fn ($cost): array => [
                        'id' => $cost->id,
                        'cost_type' => $cost->cost_type,
                        'treatment' => $cost->treatment?->value ?? (string) $cost->treatment,
                        'amount' => (float) $cost->amount,
                        'vat_amount' => (float) $cost->vat_amount,
                    ])
                    ->values()
                    ->all() ?? [],
            ];
        }

        return $items;
    }

    public function syncGrnBillingStatus(GoodsReceivedNote $grn): void
    {
        $grn->loadMissing('grnItems');

        $hasRemaining = false;
        $hasBilled = false;

        foreach ($grn->grnItems as $item) {
            $receivedQty = (float) $item->received_qty;
            $billedQty = (float) $item->billed_qty;

            if ($billedQty > 0) {
                $hasBilled = true;
            }

            if ($billedQty < $receivedQty) {
                $hasRemaining = true;
            }
        }

        $status = match (true) {
            ! $hasBilled => GrnBillingStatusEnum::OPEN,
            $hasRemaining => GrnBillingStatusEnum::PARTIALLY_BILLED,
            default => GrnBillingStatusEnum::FULLY_BILLED,
        };

        $grn->update(['billing_status' => $status->value]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncGrnItems(GoodsReceivedNote $grn, array $items): void
    {
        foreach ($items as $item) {
            $grn->grnItems()->create([
                'product_variant_id' => $item['product_variant_id'],
                'purchase_order_item_id' => $item['purchase_order_item_id'] ?? null,
                'unit_id' => $item['unit_id'] ?? null,
                'ordered_qty' => $item['ordered_qty'] ?? 0,
                'received_qty' => $item['received_qty'],
                'unit_cost' => $item['unit_cost'],
                'batch_id' => $item['batch_id'] ?? null,
                'batch_no' => $item['batch_no'] ?? null,
                'expiry_date' => $item['expiry_date'] ?? null,
                'billed_qty' => 0,
            ]);
        }
    }

    private function applyInventoryReceipts(GoodsReceivedNote $grn, Company $company, int $userId): void
    {
        foreach ($grn->grnItems as $item) {
            $qty = (int) round((float) $item->received_qty);
            if ($qty <= 0) {
                continue;
            }

            $item->loadMissing('productVariant.product');
            if ($item->productVariant?->isService()) {
                continue;
            }

            $unitCost = InventoryCostCalculator::unitCostFromGrnItem($item);
            $batchId = $item->batch_id ?? $this->batchResolver->resolve($item, $company->id, (int) $grn->warehouse_id, $unitCost);

            $this->inventoryReceipt->receive(
                $company,
                $grn,
                $item->product_variant_id,
                (int) $grn->warehouse_id,
                $qty,
                $unitCost,
                ChangeTypeEnum::GRN_RECEIPT,
                $userId,
                $grn->remarks,
                null,
                $item->id,
                null,
                $batchId,
            );
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validatePurchaseOrderQuantities(array $validated, ?int $excludeGrnId = null): void
    {
        $purchaseOrderId = $validated['purchase_order_id'] ?? null;
        if (! $purchaseOrderId) {
            return;
        }

        $receivedByPoItem = $this->receivedQuantitiesByPurchaseOrderItem((int) $purchaseOrderId, $excludeGrnId);

        foreach ($validated['items'] ?? [] as $index => $item) {
            $poItemId = $item['purchase_order_item_id'] ?? null;
            if (! $poItemId) {
                continue;
            }

            $poItem = PurchaseOrderItem::query()
                ->where('purchase_order_id', $purchaseOrderId)
                ->where('id', $poItemId)
                ->first();

            if (! $poItem) {
                throw ValidationException::withMessages([
                    "items.{$index}.purchase_order_item_id" => __('Invalid purchase order line.'),
                ]);
            }

            $orderedQty = (float) $poItem->quantity;
            $alreadyReceived = (float) ($receivedByPoItem[$poItemId] ?? 0);
            $remainingQty = max(0, $orderedQty - $alreadyReceived);
            $requestedQty = (float) ($item['received_qty'] ?? 0);

            if ($requestedQty > $remainingQty) {
                throw ValidationException::withMessages([
                    "items.{$index}.received_qty" => __('Quantity exceeds remaining receivable quantity (:remaining).', [
                        'remaining' => $remainingQty,
                    ]),
                ]);
            }
        }
    }

    /**
     * @return array<int, float>
     */
    private function receivedQuantitiesByPurchaseOrderItem(int $purchaseOrderId, ?int $excludeGrnId = null): array
    {
        $query = GrnItem::query()
            ->selectRaw('grn_items.purchase_order_item_id, SUM(grn_items.received_qty) as total_qty')
            ->join('goods_received_notes', 'goods_received_notes.id', '=', 'grn_items.goods_received_note_id')
            ->where('goods_received_notes.purchase_order_id', $purchaseOrderId)
            ->where('goods_received_notes.status', StatusEnum::APPROVED->value)
            ->whereNotNull('grn_items.purchase_order_item_id')
            ->groupBy('grn_items.purchase_order_item_id');

        if ($excludeGrnId) {
            $query->where('goods_received_notes.id', '!=', $excludeGrnId);
        }

        return $query->pluck('total_qty', 'purchase_order_item_id')
            ->map(fn ($qty) => (float) $qty)
            ->all();
    }
}
