<?php

namespace App\Services\DataTransfer;

use App\Models\Product;
use App\Enums\EntityCodeType;
use App\Enums\ProductTypeEnum;
use App\Models\ProductVariant;
use App\Models\DataTransferJob;
use Illuminate\Support\Facades\DB;
use App\Services\EntityCodeGenerator;

class ProductImportService
{
    public function __construct(
        private EntityCodeGenerator $codeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, product_id: int, variant_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, ProductImportLookupCache $lookups): array
    {
        $duplicateMode = $job->options['duplicate_mode'] ?? 'update';
        $companyId = $job->company_id;

        $existing = null;
        if (! empty($normalized['code'])) {
            $existing = Product::query()
                ->where('company_id', $companyId)
                ->where('code', $normalized['code'])
                ->first();
        }

        if ($existing && $duplicateMode === 'skip') {
            return ['action' => 'skipped', 'product_id' => $existing->id, 'variant_id' => 0];
        }

        if ($existing && $duplicateMode === 'create_only') {
            throw new \RuntimeException('Product code already exists.');
        }

        return DB::transaction(function () use ($job, $normalized, $existing, $companyId) {
            $isService = ($normalized['product_type'] ?? '') === ProductTypeEnum::SERVICE->value;

            $productData = [
                'product_category_id' => $normalized['product_category_id'],
                'product_type' => $normalized['product_type'],
                'name' => $normalized['name'],
                'code' => $normalized['code'],
                'hsn_code' => $normalized['hsn_code'],
                'unit_id' => $normalized['unit_id'],
                'brand_id' => $isService ? null : $normalized['brand_id'],
                'tax_id' => $normalized['tax_id'],
                'has_variants' => $isService ? false : (bool) $normalized['has_variants'],
                'reorder_quantity' => $isService ? 0 : ($normalized['reorder_quantity'] ?? 0),
                'min_stock_level' => $isService ? 0 : ($normalized['min_stock_level'] ?? 0),
                'description' => $normalized['description'],
            ];

            $action = 'imported';

            if ($existing) {
                $existing->update($productData);
                $product = $existing;
                $action = 'updated';
            } else {
                if (blank($productData['code'])) {
                    $productData['code'] = $this->codeGenerator->generateForType(
                        EntityCodeType::Product,
                        $companyId,
                    );
                }
                $productData['company_id'] = $companyId;
                $productData['import_batch_id'] = $job->batch_id;
                $product = Product::create($productData);
            }

            $variantPayload = $normalized['variant'];
            $variant = $this->upsertVariant($product, $variantPayload, (bool) $normalized['has_variants']);

            return [
                'action' => $action,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $variantPayload
     */
    private function upsertVariant(Product $product, array $variantPayload, bool $hasVariants): ProductVariant
    {
        $isService = $product->isService();

        $query = $product->variants();
        if (! empty($variantPayload['sku'])) {
            $existing = (clone $query)->where('sku', $variantPayload['sku'])->first();
        } elseif (! empty($variantPayload['barcode'])) {
            $existing = (clone $query)->where('barcode', $variantPayload['barcode'])->first();
        } else {
            $existing = (clone $query)->where('is_default', true)->first()
                ?? $query->first();
        }

        $data = [
            'sku' => $isService ? null : ($variantPayload['sku'] ?? null),
            'barcode' => $isService ? null : ($variantPayload['barcode'] ?? null),
            'sales_price' => $variantPayload['sales_price'] ?? 0,
            'purchase_price' => $isService ? 0 : ($variantPayload['purchase_price'] ?? 0),
            'is_default' => $hasVariants ? (bool) ($variantPayload['is_default'] ?? false) : true,
        ];

        if ($existing) {
            $existing->update($data);
            $existing->variantOptions()->sync($variantPayload['attribute_values'] ?? []);

            return $existing;
        }

        $variant = $product->variants()->create([
            'company_id' => $product->company_id,
            ...$data,
        ]);
        $variant->variantOptions()->attach($variantPayload['attribute_values'] ?? []);

        return $variant;
    }
}
