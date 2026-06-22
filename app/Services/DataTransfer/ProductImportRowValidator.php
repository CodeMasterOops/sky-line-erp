<?php

namespace App\Services\DataTransfer;

use Illuminate\Support\Facades\Validator;
use App\Services\DataTransfer\Import\ImportRowValidatorInterface;

class ProductImportRowValidator implements ImportRowValidatorInterface
{
    /**
     * Built-in synonyms for the product_type column.
     *
     * @var array<string, string>
     */
    private const PRODUCT_TYPE_ALIASES = [
        'product' => 'product',
        'products' => 'product',
        'goods' => 'product',
        'good' => 'product',
        'item' => 'product',
        'items' => 'product',
        'stock' => 'product',
        'service' => 'service',
        'services' => 'service',
        'svc' => 'service',
        'labour' => 'service',
        'labor' => 'service',
    ];

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{normalized: array<string, mixed>, errors: list<string>, field_errors: list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>, skip: bool}
     */
    public function validate(array $row, mixed $lookups, array $context = []): array
    {
        if (! $lookups instanceof ProductImportLookupCache) {
            return [
                'normalized' => $row,
                'errors' => ['Invalid lookup cache for product import.'],
                'field_errors' => [],
                'skip' => false,
            ];
        }

        return $this->validateProduct($row, $lookups);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{normalized: array<string, mixed>, errors: list<string>, field_errors: list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>, skip: bool}
     */
    private function validateProduct(array $row, ProductImportLookupCache $lookups): array
    {
        $errors = [];
        $fieldErrors = [];
        $skip = false;

        $productType = $this->resolveProductType($row['product_type'] ?? null, $lookups, $errors, $fieldErrors);

        $categoryId = $this->resolveField('category', $row['category'] ?? null, true, $lookups, $errors, $fieldErrors, $skip);

        $unitId = $this->resolveField('unit', $row['unit'] ?? null, true, $lookups, $errors, $fieldErrors, $skip);

        $brandId = null;
        if ($productType !== 'service' && ! empty($row['brand'])) {
            $brandId = $this->resolveField('brand', $row['brand'], false, $lookups, $errors, $fieldErrors, $skip);
        }

        $taxId = null;
        if (! empty($row['tax'])) {
            $taxId = $this->resolveField('tax', $row['tax'], false, $lookups, $errors, $fieldErrors, $skip);
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
            'name' => $this->trimOrNull($row['name'] ?? null),
            'code' => $this->trimOrNull($row['code'] ?? null),
            'product_type' => $productType,
            'hsn_code' => $this->trimOrNull($row['hsn_code'] ?? null),
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
                'sku' => $this->trimOrNull($row['sku'] ?? null),
                'barcode' => $this->trimOrNull($row['barcode'] ?? null),
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
            'field_errors' => $fieldErrors,
            'skip' => $skip,
        ];
    }

    /**
     * @param  list<string>  $errors
     * @param  list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>  $fieldErrors
     */
    private function resolveProductType(mixed $value, ProductImportLookupCache $lookups, array &$errors, array &$fieldErrors): string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return 'product';
        }

        $key = strtolower($raw);

        if ($alias = $lookups->resolveProductTypeAlias($raw)) {
            return in_array($alias, ['product', 'service'], true) ? $alias : 'product';
        }

        if (isset(self::PRODUCT_TYPE_ALIASES[$key])) {
            return self::PRODUCT_TYPE_ALIASES[$key];
        }

        $suggestion = $this->closestProductType($key);
        $errors[] = "Product type '{$raw}' is not recognised. Use 'product' or 'service'.";
        $fieldErrors[] = [
            'field' => 'product_type',
            'value' => $raw,
            'message' => "Unrecognised product type. Did you mean '{$suggestion}'?",
            'suggestions' => [
                ['id' => 0, 'label' => 'product'],
                ['id' => 0, 'label' => 'service'],
            ],
        ];

        return $suggestion;
    }

    private function closestProductType(string $value): string
    {
        similar_text($value, 'service', $serviceScore);
        similar_text($value, 'product', $productScore);

        return $serviceScore > $productScore ? 'service' : 'product';
    }

    /**
     * @param  list<string>  $errors
     * @param  list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>  $fieldErrors
     */
    private function resolveField(
        string $field,
        mixed $value,
        bool $required,
        ProductImportLookupCache $lookups,
        array &$errors,
        array &$fieldErrors,
        bool &$skip,
    ): ?int {
        $raw = trim((string) ($value ?? ''));

        if ($raw === '') {
            if ($required) {
                $errors[] = ucfirst($field).' is required.';
            }

            return null;
        }

        $match = $lookups->match($field, $raw);

        if ($match->isMatched()) {
            return $match->id;
        }

        if ($match->isSkip()) {
            if ($required) {
                $skip = true;
            }

            return null;
        }

        $message = $match->suggestions === []
            ? ucfirst($field)." '{$raw}' was not found."
            : ucfirst($field)." '{$raw}' was not found. Did you mean '{$match->suggestions[0]['label']}'?";

        $errors[] = $message;
        $fieldErrors[] = [
            'field' => $field,
            'value' => $raw,
            'message' => $message,
            'suggestions' => $match->suggestions,
        ];

        return null;
    }

    private function trimOrNull(mixed $value): ?string
    {
        $trimmed = trim((string) ($value ?? ''));

        return $trimmed === '' ? null : $trimmed;
    }
}
