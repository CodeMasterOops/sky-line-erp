<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\StockLayer;
use App\Models\InvoiceItem;
use App\Enums\ChangeTypeEnum;
use App\Models\StockMovement;
use App\Enums\StockDirectionEnum;

class SalesReturnUnitCostResolver
{
    /**
     * Prefer COGS from the linked sales invoice movements; optional invoice line for FIFO-accurate line matching.
     * Otherwise weighted average of open layers.
     */
    public function resolve(
        Company $company,
        ?int $invoiceId,
        int $productVariantId,
        int $warehouseId,
        ?int $invoiceItemId = null,
    ): float {
        if ($invoiceId && $invoiceItemId) {
            $lineCost = $this->resolveFromMatchedInvoiceLine(
                $company,
                $invoiceId,
                $invoiceItemId,
                $productVariantId,
                $warehouseId
            );
            if ($lineCost !== null) {
                return $lineCost;
            }
        }

        if ($invoiceId) {
            $movements = StockMovement::withoutGlobalScopes()
                ->where('company_id', $company->id)
                ->where('reference_type', (new Invoice)->getMorphClass())
                ->where('reference_id', $invoiceId)
                ->where('product_variant_id', $productVariantId)
                ->where('warehouse_id', $warehouseId)
                ->where('direction', StockDirectionEnum::OUT)
                ->where('type', ChangeTypeEnum::SALE)
                ->get();

            $qty = (int) $movements->sum('quantity');
            if ($qty > 0) {
                $cost = (float) $movements->sum('total_cost');

                return round($cost / $qty, 4);
            }
        }

        return $this->fallbackWeightedAverageFromLayers($company, $productVariantId, $warehouseId);
    }

    private function resolveFromMatchedInvoiceLine(
        Company $company,
        int $invoiceId,
        int $invoiceItemId,
        int $productVariantId,
        int $warehouseId,
    ): ?float {
        $item = InvoiceItem::withoutGlobalScopes()
            ->where('id', $invoiceItemId)
            ->where('invoice_id', $invoiceId)
            ->first();

        if (! $item
            || (int) $item->product_variant_id !== $productVariantId
            || (int) $item->warehouse_id !== $warehouseId) {
            return null;
        }

        $orderedItemIds = InvoiceItem::withoutGlobalScopes()
            ->where('invoice_id', $invoiceId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $index = array_search($invoiceItemId, $orderedItemIds, true);
        if ($index === false) {
            return null;
        }

        $movements = StockMovement::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('reference_type', (new Invoice)->getMorphClass())
            ->where('reference_id', $invoiceId)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('direction', StockDirectionEnum::OUT)
            ->where('type', ChangeTypeEnum::SALE)
            ->orderBy('id')
            ->get()
            ->values();

        if (! isset($movements[$index])) {
            return null;
        }

        $m = $movements[$index];
        $q = (int) $m->quantity;
        if ($q <= 0) {
            return null;
        }

        return round((float) $m->total_cost / $q, 4);
    }

    private function fallbackWeightedAverageFromLayers(
        Company $company,
        int $productVariantId,
        int $warehouseId,
    ): float {
        $layers = StockLayer::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('qty_remaining', '>', 0)
            ->get();

        $qty = (int) $layers->sum('qty_remaining');
        if ($qty <= 0) {
            return 0.0;
        }

        $cost = 0.0;
        foreach ($layers as $layer) {
            $cost += (int) $layer->qty_remaining * (float) $layer->unit_cost;
        }

        return round($cost / $qty, 4);
    }
}
