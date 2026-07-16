<?php

namespace App\Services\DataTransfer;

use App\Models\Warehouse;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Cache;

class OpeningStockImportLookupCache
{
    /** @var array<string, int> */
    private array $variantsBySku = [];

    /** @var array<string, int> */
    private array $variantsByBarcode = [];

    /** @var array<string, int> */
    private array $defaultVariantByProductCode = [];

    /** @var array<string, int> */
    private array $warehousesByKey = [];

    /**
     * Per-variant metadata needed at import time.
     *
     * @var array<int, array{unit_id: int|null, is_service: bool, purchase_price: float, label: string}>
     */
    private array $variantMeta = [];

    public function __construct(private int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return Cache::remember(
            "dt:os-lookups:{$companyId}",
            300,
            fn () => (new self($companyId))->warm()
        );
    }

    public static function forget(int $companyId): void
    {
        Cache::forget("dt:os-lookups:{$companyId}");
    }

    public function warm(): self
    {
        ProductVariant::query()
            ->where('company_id', $this->companyId)
            ->with('product:id,code,unit_id,product_type')
            ->get(['id', 'product_id', 'sku', 'barcode', 'purchase_price', 'is_default'])
            ->each(function (ProductVariant $variant) {
                $product = $variant->product;
                $isService = $product?->product_type === ProductTypeEnum::SERVICE;

                if ($variant->sku) {
                    $this->variantsBySku[strtolower($variant->sku)] = $variant->id;
                }

                if ($variant->barcode) {
                    $this->variantsByBarcode[strtolower($variant->barcode)] = $variant->id;
                }

                if ($product?->code) {
                    $codeKey = strtolower($product->code);
                    if ($variant->is_default || ! isset($this->defaultVariantByProductCode[$codeKey])) {
                        $this->defaultVariantByProductCode[$codeKey] = $variant->id;
                    }
                }

                $this->variantMeta[$variant->id] = [
                    'unit_id' => $product?->unit_id,
                    'is_service' => $isService,
                    'purchase_price' => (float) $variant->purchase_price,
                    'label' => $variant->sku ?? ($product?->code ?? (string) $variant->id),
                ];
            });

        Warehouse::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function (Warehouse $warehouse) {
                $this->warehousesByKey[strtolower($warehouse->name)] = $warehouse->id;
                if ($warehouse->code) {
                    $this->warehousesByKey[strtolower($warehouse->code)] = $warehouse->id;
                }
            });

        return $this;
    }

    /**
     * Resolve a variant by SKU, then barcode, then the product code's default variant.
     */
    public function resolveVariant(?string $sku, ?string $barcode, ?string $productCode): ?int
    {
        if ($sku && isset($this->variantsBySku[strtolower(trim($sku))])) {
            return $this->variantsBySku[strtolower(trim($sku))];
        }

        if ($barcode && isset($this->variantsByBarcode[strtolower(trim($barcode))])) {
            return $this->variantsByBarcode[strtolower(trim($barcode))];
        }

        if ($productCode && isset($this->defaultVariantByProductCode[strtolower(trim($productCode))])) {
            return $this->defaultVariantByProductCode[strtolower(trim($productCode))];
        }

        return null;
    }

    /**
     * @return array{unit_id: int|null, is_service: bool, purchase_price: float, label: string}|null
     */
    public function variantMeta(int $variantId): ?array
    {
        return $this->variantMeta[$variantId] ?? null;
    }

    public function resolveWarehouse(?string $value): ?int
    {
        return $value ? ($this->warehousesByKey[strtolower(trim($value))] ?? null) : null;
    }
}
