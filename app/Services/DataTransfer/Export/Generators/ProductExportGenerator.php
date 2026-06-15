<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\Product;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class ProductExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return config('data_transfer.product_fields', []);
    }

    public function generate(DataTransferJob $job, string $absolutePath): void
    {
        $format = $job->options['format'] ?? 'csv';
        $filters = $job->options['filters'] ?? [];
        $writer = new ExportWriter;
        $writer->open($absolutePath, $format);
        $writer->addRow($this->headers());

        Product::query()
            ->with([
                'productCategory.parent:id,name',
                'unit:id,name,code',
                'brand:id,name,code',
                'tax:id,name',
                'variants.variantOptions.attribute',
            ])
            ->filter($filters)
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Product $product) use ($writer, $job) {
                foreach ($product->variants as $variant) {
                    $attrs = $variant->variantOptions->values();
                    $writer->addRow([
                        $product->name,
                        $product->code,
                        $product->product_type?->value,
                        $product->hsn_code,
                        $product->description,
                        $product->productCategory?->fullPath(),
                        $product->unit?->name,
                        $product->brand?->name,
                        $product->tax?->name,
                        $product->has_variants ? '1' : '0',
                        (string) $product->reorder_quantity,
                        (string) $product->min_stock_level,
                        $variant->sku,
                        $variant->barcode,
                        (string) $variant->sales_price,
                        (string) $variant->purchase_price,
                        $variant->is_default ? '1' : '0',
                        $attrs[0]?->attribute?->name ?? '',
                        $attrs[0]?->value ?? '',
                        $attrs[1]?->attribute?->name ?? '',
                        $attrs[1]?->value ?? '',
                        '',
                        '',
                    ]);
                    $job->incrementStat('processed');
                }
            });

        $writer->close();
    }
}
