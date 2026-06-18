<?php

namespace App\Services\Inventory;

use App\Models\Company;
use App\Models\SerialNumber;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

class SerialNumberService
{
    /**
     * Record incoming serial numbers linked to a receipt stock movement.
     *
     * @param  string[]  $serials
     */
    public function bulkReceive(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        array $serials,
        StockMovement $movement,
    ): void {
        $this->validateNoDuplicatesInBatch($serials);
        $this->validateNotAlreadyInStock($company, $productVariantId, $serials);

        foreach ($serials as $serial) {
            SerialNumber::create([
                'company_id' => $company->id,
                'product_variant_id' => $productVariantId,
                'serial_no' => $serial,
                'status' => 'in_stock',
                'warehouse_id' => $warehouseId,
                'receipt_movement_id' => $movement->id,
            ]);
        }
    }

    /**
     * Mark serial numbers as issued, linked to an issue stock movement.
     *
     * @param  string[]  $serials
     */
    public function bulkIssue(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        array $serials,
        StockMovement $movement,
    ): void {
        $this->validateAvailable($company, $productVariantId, $warehouseId, $serials);

        SerialNumber::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('serial_no', $serials)
            ->where('status', 'in_stock')
            ->update([
                'status' => 'issued',
                'warehouse_id' => null,
                'issue_movement_id' => $movement->id,
            ]);
    }

    /**
     * Validate that all given serials are in_stock at the warehouse.
     *
     * @param  string[]  $serials
     *
     * @throws ValidationException
     */
    public function validateAvailable(
        Company $company,
        int $productVariantId,
        int $warehouseId,
        array $serials,
    ): void {
        $available = SerialNumber::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->where('warehouse_id', $warehouseId)
            ->where('status', 'in_stock')
            ->whereIn('serial_no', $serials)
            ->pluck('serial_no')
            ->all();

        $missing = array_values(array_diff($serials, $available));

        if (! empty($missing)) {
            throw ValidationException::withMessages([
                'serials' => __('Serial numbers not available in stock: '.implode(', ', $missing)),
            ]);
        }
    }

    /** @param  string[]  $serials */
    private function validateNoDuplicatesInBatch(array $serials): void
    {
        $unique = array_unique($serials);

        if (count($unique) !== count($serials)) {
            throw ValidationException::withMessages([
                'serials' => __('Duplicate serial numbers in the batch.'),
            ]);
        }
    }

    /** @param  string[]  $serials */
    private function validateNotAlreadyInStock(Company $company, int $productVariantId, array $serials): void
    {
        $existing = SerialNumber::withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->where('product_variant_id', $productVariantId)
            ->whereIn('serial_no', $serials)
            ->where('status', 'in_stock')
            ->pluck('serial_no')
            ->all();

        if (! empty($existing)) {
            throw ValidationException::withMessages([
                'serials' => __('Serial numbers already in stock: '.implode(', ', $existing)),
            ]);
        }
    }
}
