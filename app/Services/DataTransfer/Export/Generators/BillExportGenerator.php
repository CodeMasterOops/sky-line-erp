<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\Bill;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class BillExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return [
            'bill_no', 'bill_date', 'due_date', 'status', 'party_code', 'party_name',
            'sku', 'product_name', 'quantity', 'rate', 'line_total',
        ];
    }

    public function generate(DataTransferJob $job, string $absolutePath): void
    {
        $format = $job->options['format'] ?? 'csv';
        $filters = $job->options['filters'] ?? [];
        $writer = new ExportWriter;
        $writer->open($absolutePath, $format);
        $writer->addRow($this->headers());

        $query = Bill::query()->with(['party:id,code,name', 'billItems.productVariant.product:id,name']);

        if (! empty($filters['start_date'])) {
            $query->whereDate('bill_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('bill_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($job->branch_id) {
            $query->where('branch_id', $job->branch_id);
        }

        $query->orderBy('id')->lazyById(100)->each(function (Bill $bill) use ($writer, $job) {
            foreach ($bill->billItems as $item) {
                $writer->addRow([
                    $bill->bill_no,
                    $bill->bill_date?->format('Y-m-d'),
                    $bill->due_date?->format('Y-m-d'),
                    $bill->status?->value ?? $bill->status,
                    $bill->party?->code,
                    $bill->party?->name,
                    $item->productVariant?->sku,
                    $item->productVariant?->product?->name,
                    (string) $item->quantity,
                    (string) $item->rate,
                    (string) ($item->quantity * $item->rate),
                ]);
                $job->incrementStat('processed');
            }
        });

        $writer->close();
    }
}
