<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\Stock;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class StockExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return ['product_code', 'product_name', 'sku', 'warehouse', 'branch_id', 'quantity', 'on_hold'];
    }

    public function generate(DataTransferJob $job, string $absolutePath): void
    {
        $format = $job->options['format'] ?? 'csv';
        $filters = $job->options['filters'] ?? [];
        $writer = new ExportWriter;
        $writer->open($absolutePath, $format);
        $writer->addRow($this->headers());

        $query = Stock::query()
            ->where('company_id', $job->company_id)
            ->with([
                'productVariant.product:id,name,code',
                'warehouse:id,name,code',
            ]);

        if ($job->branch_id) {
            $query->where('branch_id', $job->branch_id);
        } elseif (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }
        if (! empty($filters['warehouse_id'])) {
            $query->where('warehouse_id', $filters['warehouse_id']);
        }

        $query->orderBy('id')->lazyById(500)->each(function (Stock $stock) use ($writer, $job) {
            $variant = $stock->productVariant;
            $product = $variant?->product;
            $writer->addRow([
                $product?->code,
                $product?->name,
                $variant?->sku,
                $stock->warehouse?->name,
                (string) $stock->branch_id,
                (string) $stock->quantity,
                (string) $stock->on_hold,
            ]);
            $job->incrementStat('processed');
        });

        $writer->close();
    }
}
