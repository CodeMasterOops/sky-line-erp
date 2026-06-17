<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\SalesOrder;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class SalesOrderExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return [
            'order_no', 'order_date', 'status', 'party_code', 'party_name',
            'sku', 'product_name', 'quantity', 'rate',
        ];
    }

    public function generate(DataTransferJob $job, string $absolutePath): void
    {
        $format = $job->options['format'] ?? 'csv';
        $filters = $job->options['filters'] ?? [];
        $writer = new ExportWriter;
        $writer->open($absolutePath, $format);
        $writer->addRow($this->headers());

        $query = SalesOrder::query()
            ->where('company_id', $job->company_id)
            ->with(['party:id,code,name', 'salesOrderItems.productVariant.product:id,name']);

        if (! empty($filters['start_date'])) {
            $query->whereDate('order_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('order_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($job->branch_id) {
            $query->where('branch_id', $job->branch_id);
        }

        $query->orderBy('id')->lazyById(100)->each(function (SalesOrder $order) use ($writer, $job) {
            foreach ($order->salesOrderItems as $item) {
                $writer->addRow([
                    $order->order_no,
                    $order->order_date,
                    $order->status?->value ?? $order->status,
                    $order->party?->code,
                    $order->party?->name,
                    $item->productVariant?->sku,
                    $item->productVariant?->product?->name,
                    (string) $item->quantity,
                    (string) $item->rate,
                ]);
                $job->incrementStat('processed');
            }
        });

        $writer->close();
    }
}
