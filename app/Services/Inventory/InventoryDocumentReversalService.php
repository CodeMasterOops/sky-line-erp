<?php

namespace App\Services\Inventory;

use App\Models\Bill;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\DebitNote;
use App\Models\CreditNote;
use App\Models\StockLayer;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\StockDirectionEnum;
use Illuminate\Validation\ValidationException;

class InventoryDocumentReversalService
{
    public function __construct(
        private StockQuantityService $quantities,
        private InventoryLayerIssueService $inventoryIssue,
    ) {}

    public function reverseApprovedInvoice(Invoice $invoice, ?int $userId, ?string $remarks = null): void
    {
        $movements = StockMovement::withoutGlobalScopes()
            ->where('company_id', $invoice->company_id)
            ->where('reference_type', $invoice->getMorphClass())
            ->where('reference_id', $invoice->id)
            ->where('direction', StockDirectionEnum::OUT)
            ->where('type', ChangeTypeEnum::SALE)
            ->with('movementLayers')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            foreach ($movement->movementLayers as $ml) {
                $layer = StockLayer::withoutGlobalScopes()
                    ->where('id', $ml->stock_layer_id)
                    ->lockForUpdate()
                    ->first();

                if (! $layer) {
                    throw ValidationException::withMessages([
                        'invoice' => __('Cannot void invoice: a cost layer referenced by this sale is missing.'),
                    ]);
                }

                $layer->qty_remaining = (int) $layer->qty_remaining + (int) $ml->quantity;
                $layer->save();
            }

            $this->quantities->adjust(
                $invoice->company_id,
                $movement->product_variant_id,
                $movement->warehouse_id,
                (int) $movement->quantity,
            );

            $invoice->stockMovements()->create([
                'company_id' => $invoice->company_id,
                'product_variant_id' => $movement->product_variant_id,
                'warehouse_id' => $movement->warehouse_id,
                'type' => ChangeTypeEnum::RETURN_IN,
                'direction' => StockDirectionEnum::IN,
                'quantity' => $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'total_cost' => $movement->total_cost,
                'user_id' => $userId,
                'remarks' => $remarks,
            ]);
        }
    }

    public function reverseApprovedBill(Bill $bill, ?int $userId, ?string $remarks = null): void
    {
        $bill->loadMissing('billItems');

        foreach ($bill->billItems as $item) {
            $qty = (int) $item->quantity;
            if ($qty <= 0) {
                continue;
            }

            $layers = StockLayer::withoutGlobalScopes()
                ->where('company_id', $bill->company_id)
                ->where('source_bill_item_id', $item->id)
                ->where('qty_remaining', '>', 0)
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            $remainingValued = (int) $layers->sum('qty_remaining');
            if ($remainingValued < $qty) {
                throw ValidationException::withMessages([
                    'bill' => __('Cannot void bill: some purchased quantity was already sold or adjusted.'),
                ]);
            }

            $toRemove = $qty;
            foreach ($layers as $layer) {
                if ($toRemove <= 0) {
                    break;
                }
                $layerQty = (int) $layer->qty_remaining;
                $take = min($layerQty, $toRemove);
                $layer->qty_remaining = $layerQty - $take;
                $layer->save();
                $toRemove -= $take;
            }

            $this->quantities->adjust(
                $bill->company_id,
                $item->product_variant_id,
                $item->warehouse_id,
                -$qty,
            );

            $unitCost = InventoryCostCalculator::unitCostFromBillItem($item);
            $totalCost = round($qty * $unitCost, 4);

            $bill->stockMovements()->create([
                'company_id' => $bill->company_id,
                'product_variant_id' => $item->product_variant_id,
                'warehouse_id' => $item->warehouse_id,
                'type' => ChangeTypeEnum::RETURN_OUT,
                'direction' => StockDirectionEnum::OUT,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'user_id' => $userId,
                'remarks' => $remarks,
            ]);
        }
    }

    public function reverseApprovedDebitNote(DebitNote $debitNote, ?int $userId, ?string $remarks = null): void
    {
        $movements = StockMovement::withoutGlobalScopes()
            ->where('company_id', $debitNote->company_id)
            ->where('reference_type', $debitNote->getMorphClass())
            ->where('reference_id', $debitNote->id)
            ->where('direction', StockDirectionEnum::OUT)
            ->where('type', ChangeTypeEnum::RETURN_OUT)
            ->with('movementLayers')
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            foreach ($movement->movementLayers as $ml) {
                $layer = StockLayer::withoutGlobalScopes()
                    ->where('id', $ml->stock_layer_id)
                    ->lockForUpdate()
                    ->first();

                if (! $layer) {
                    throw ValidationException::withMessages([
                        'debit_note' => __('Cannot void debit note: a cost layer referenced by this return is missing.'),
                    ]);
                }

                $layer->qty_remaining = (int) $layer->qty_remaining + (int) $ml->quantity;
                $layer->save();
            }

            $this->quantities->adjust(
                $debitNote->company_id,
                $movement->product_variant_id,
                $movement->warehouse_id,
                (int) $movement->quantity,
            );

            $debitNote->stockMovements()->create([
                'company_id' => $debitNote->company_id,
                'product_variant_id' => $movement->product_variant_id,
                'warehouse_id' => $movement->warehouse_id,
                'type' => ChangeTypeEnum::PURCHASE,
                'direction' => StockDirectionEnum::IN,
                'quantity' => $movement->quantity,
                'unit_cost' => $movement->unit_cost,
                'total_cost' => $movement->total_cost,
                'user_id' => $userId,
                'remarks' => $remarks,
            ]);
        }
    }

    public function reverseApprovedCreditNote(CreditNote $creditNote, Company $company, ?int $userId, ?string $remarks = null): void
    {
        $movements = StockMovement::withoutGlobalScopes()
            ->where('company_id', $creditNote->company_id)
            ->where('reference_type', $creditNote->getMorphClass())
            ->where('reference_id', $creditNote->id)
            ->where('direction', StockDirectionEnum::IN)
            ->where('type', ChangeTypeEnum::RETURN_IN)
            ->orderBy('id')
            ->get();

        foreach ($movements as $movement) {
            $this->inventoryIssue->issue(
                $company,
                $creditNote,
                (int) $movement->product_variant_id,
                (int) $movement->warehouse_id,
                (int) $movement->quantity,
                ChangeTypeEnum::ADJUSTMENT_OUT,
                $userId,
                $remarks,
            );
        }
    }
}
