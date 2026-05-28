<?php

namespace App\Services\DataTransfer;

use Illuminate\Support\Facades\Validator;

class ProductImportRowValidator
{
    /**
     * @param  array<string, mixed>  $row
     * @return array{normalized: array<string, mixed>, errors: list<string>}
     */
    public function validate(array $row, ProductImportLookupCache $lookups): array
    {
        $errors = [];

        $productType = strtolower((string) ($row['product_type'] ?? 'product'));
        if (! in_array($productType, ['product', 'service'], true)) {
            $productType = 'product';
        }

        $categoryId = $lookups->resolveCategory($row['category'] ?? null);
        if (! $categoryId) {
            $errors[] = 'Category is required and must match an existing category name.';
        }

        $unitId = $lookups->resolveUnit($row['unit'] ?? null);
        if (! $unitId) {
            $errors[] = 'Unit is required and must match an existing unit name or code.';
        }

        $brandId = null;
        if ($productType !== 'service' && ! empty($row['brand'])) {
            $brandId = $lookups->resolveBrand($row['brand']);
            if (! $brandId) {
                $errors[] = 'Brand not found.';
            }
        }

        $taxId = null;
        if (! empty($row['tax'])) {
            $taxId = $lookups->resolveTax($row['tax']);
            if (! $taxId) {
                $errors[] = 'Tax must match an existing VAT rate name.';
            }
        }

        if (empty($row['name'])) {
            $errors[] = 'Product name is required.';
        }

        if (! isset($row['sales_price']) || $row['sales_price'] === '') {
            $errors[] = 'Sales price is required.';
        }

        if ($productType === 'product' && (! isset($row['purchase_price']) || $row['purchase_price'] === '')) {
            $errors[] = 'Purchase price is required for products.';
        }

        $hasVariants = filter_var($row['has_variants'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $attributeValueIds = [];

        if ($hasVariants && $productType === 'product') {
            for ($i = 1; $i <= 2; $i++) {
                $attrName = $row["attribute_{$i}_name"] ?? null;
                $attrValue = $row["attribute_{$i}_value"] ?? null;
                if ($attrName && $attrValue) {
                    $id = $lookups->resolveAttributeValue($attrName, $attrValue);
                    if (! $id) {
                        $errors[] = "Attribute option not found: {$attrName} = {$attrValue}.";
                    } else {
                        $attributeValueIds[] = $id;
                    }
                }
            }
            if ($attributeValueIds === []) {
                $errors[] = 'Variant products require at least one attribute option.';
            }
        }

        $warehouseId = null;
        $quantity = null;
        if (! empty($row['warehouse']) || ! empty($row['quantity'])) {
            $warehouseId = $lookups->resolveWarehouse($row['warehouse'] ?? null);
            if (! $warehouseId) {
                $errors[] = 'Warehouse not found for opening stock.';
            }
            $quantity = isset($row['quantity']) && $row['quantity'] !== ''
                ? (int) $row['quantity']
                : null;
            if ($quantity === null || $quantity < 0) {
                $errors[] = 'Opening stock quantity must be zero or greater.';
            }
        }

        $validator = Validator::make(
            ['sales_price' => $row['sales_price'] ?? null, 'purchase_price' => $row['purchase_price'] ?? 0],
            [
                'sales_price' => ['required', 'numeric', 'min:0'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $errors[] = $message;
            }
        }

        $normalized = [
            'name' => $row['name'] ?? null,
            'code' => $row['code'] ?? null,
            'product_type' => $productType,
            'hsn_code' => $row['hsn_code'] ?? null,
            'description' => $row['description'] ?? null,
            'product_category_id' => $categoryId,
            'unit_id' => $unitId,
            'brand_id' => $brandId,
            'tax_id' => $taxId,
            'has_variants' => $hasVariants,
            'reorder_quantity' => isset($row['reorder_quantity']) && $row['reorder_quantity'] !== ''
                ? (int) $row['reorder_quantity'] : 0,
            'min_stock_level' => isset($row['min_stock_level']) && $row['min_stock_level'] !== ''
                ? (float) $row['min_stock_level'] : 0,
            'variant' => [
                'sku' => $row['sku'] ?? null,
                'sales_price' => (float) ($row['sales_price'] ?? 0),
                'purchase_price' => (float) ($row['purchase_price'] ?? 0),
                'is_default' => filter_var($row['is_default'] ?? true, FILTER_VALIDATE_BOOLEAN),
                'attribute_values' => $attributeValueIds,
            ],
            'opening_stock' => $warehouseId ? [
                'warehouse_id' => $warehouseId,
                'quantity' => $quantity,
            ] : null,
        ];

        return [
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }
}
