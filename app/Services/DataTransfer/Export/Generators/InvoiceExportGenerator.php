<?php

namespace App\Services\DataTransfer\Export\Generators;

use App\Models\Invoice;
use App\Models\DataTransferJob;
use App\Services\DataTransfer\Export\ExportWriter;
use App\Services\DataTransfer\Export\ExportGeneratorInterface;

class InvoiceExportGenerator implements ExportGeneratorInterface
{
    public function headers(): array
    {
        return [
            'invoice_no', 'invoice_date', 'due_date', 'status', 'party_code', 'party_name',
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

        $query = Invoice::query()->with(['party:id,code,name', 'invoiceItems.productVariant.product:id,name']);

        if (! empty($filters['start_date'])) {
            $query->whereDate('invoice_date', '>=', $filters['start_date']);
        }
        if (! empty($filters['end_date'])) {
            $query->whereDate('invoice_date', '<=', $filters['end_date']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if ($job->branch_id) {
            $query->where('branch_id', $job->branch_id);
        }

        $query->orderBy('id')->lazyById(100)->each(function (Invoice $invoice) use ($writer, $job) {
            foreach ($invoice->invoiceItems as $item) {
                $writer->addRow([
                    $invoice->invoice_no,
                    $invoice->invoice_date?->format('Y-m-d'),
                    $invoice->due_date?->format('Y-m-d'),
                    $invoice->status?->value ?? $invoice->status,
                    $invoice->party?->code,
                    $invoice->party?->name,
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
