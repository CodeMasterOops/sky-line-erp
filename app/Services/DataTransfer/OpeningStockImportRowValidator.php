<?php

namespace App\Services\DataTransfer;

use Carbon\Carbon;
use App\Services\DataTransfer\Import\ImportRowValidatorInterface;

class OpeningStockImportRowValidator implements ImportRowValidatorInterface
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{normalized: array<string, mixed>, errors: list<string>, field_errors: list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>, skip: bool}
     */
    public function validate(array $row, mixed $lookups, array $context = []): array
    {
        if (! $lookups instanceof OpeningStockImportLookupCache) {
            return [
                'normalized' => $row,
                'errors' => ['Invalid lookup cache for opening stock import.'],
                'field_errors' => [],
                'skip' => false,
            ];
        }

        $errors = [];
        $fieldErrors = [];

        $sku = $this->trimOrNull($row['sku'] ?? null);
        $barcode = $this->trimOrNull($row['barcode'] ?? null);
        $productCode = $this->trimOrNull($row['product_code'] ?? null);

        $variantId = $lookups->resolveVariant($sku, $barcode, $productCode);
        $meta = $variantId ? $lookups->variantMeta($variantId) : null;

        if (! $variantId || ! $meta) {
            $identifier = $sku ?? $barcode ?? $productCode ?? '';
            $errors[] = "Product not found. Provide a valid SKU, barcode, or product code (received '{$identifier}').";
        } elseif ($meta['is_service']) {
            $errors[] = "Opening stock cannot be set for service items ('{$meta['label']}').";
        }

        $warehouseRaw = $this->trimOrNull($row['warehouse'] ?? null);
        if ($warehouseRaw !== null) {
            $warehouseId = $lookups->resolveWarehouse($warehouseRaw);
            if (! $warehouseId) {
                $errors[] = "Warehouse '{$warehouseRaw}' was not found.";
            }
        } else {
            $warehouseId = $lookups->resolveWarehouseId(
                isset($context['default_warehouse_id']) ? (int) $context['default_warehouse_id'] : null
            );
            if (! $warehouseId) {
                $errors[] = 'Warehouse is required.';
            }
        }

        $quantity = $this->toFloatOrNull($row['quantity'] ?? null);
        if ($quantity === null || $quantity <= 0) {
            $errors[] = 'Quantity is required and must be greater than zero.';
        }

        $rate = $this->toFloatOrNull($row['rate'] ?? null);
        if ($rate !== null && $rate < 0) {
            $errors[] = 'Rate must be zero or greater.';
        }

        if ($rate === null && $meta) {
            $rate = $meta['purchase_price'];
        }

        $batchNo = $this->trimOrNull($row['batch_no'] ?? null);
        if ($batchNo !== null && mb_strlen($batchNo) > 100) {
            $errors[] = 'Batch number must not exceed 100 characters.';
        }

        [$expiryDate, $expiryError] = $this->parseExpiryDate($row['expiry_date'] ?? null);
        if ($expiryError !== null) {
            $errors[] = $expiryError;
        }

        $normalized = [
            'product_variant_id' => $variantId,
            'unit_id' => $meta['unit_id'] ?? null,
            'warehouse_id' => $warehouseId,
            'quantity' => $quantity,
            'unit_cost' => max(0, (float) ($rate ?? 0)),
            'batch_no' => $batchNo,
            'expiry_date' => $expiryDate,
            'remarks' => $this->trimOrNull($row['remarks'] ?? null),
        ];

        return [
            'normalized' => $normalized,
            'errors' => $errors,
            'field_errors' => $fieldErrors,
            'skip' => false,
        ];
    }

    /**
     * Parse and validate the optional expiry date. Mirrors the manual
     * OpeningStockEntryRequest rule: nullable date that must be after today.
     *
     * @return array{0: string|null, 1: string|null} Tuple of [normalized Y-m-d, error]
     */
    private function parseExpiryDate(mixed $value): array
    {
        $raw = $this->trimOrNull($value);
        if ($raw === null) {
            return [null, null];
        }

        try {
            $date = Carbon::parse($raw);
        } catch (\Throwable) {
            return [null, "Expiry date '{$raw}' is not a valid date."];
        }

        if ($date->startOfDay()->lessThanOrEqualTo(Carbon::today())) {
            return [null, "Expiry date '{$raw}' must be a future date."];
        }

        return [$date->toDateString(), null];
    }

    private function trimOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }

    private function toFloatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
