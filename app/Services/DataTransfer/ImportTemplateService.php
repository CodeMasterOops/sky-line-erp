<?php

namespace App\Services\DataTransfer;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DataTransfer\Export\ExportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateService
{
    public function downloadProductTemplate(string $format = 'csv'): StreamedResponse
    {
        return $this->downloadTemplate(
            config('data_transfer.product_fields', []),
            'product-import-template',
            $format
        );
    }

    public function downloadOpeningStockTemplate(string $format = 'csv'): StreamedResponse
    {
        return $this->downloadTemplate(
            config('data_transfer.opening_stock_fields', []),
            'opening-stock-import-template',
            $format
        );
    }

    public function downloadWarehouseTemplate(string $format = 'csv'): StreamedResponse
    {
        return $this->downloadTemplate(
            config('data_transfer.warehouse_fields', []),
            'warehouse-import-template',
            $format
        );
    }

    public function downloadContactTemplate(string $format = 'csv'): StreamedResponse
    {
        return $this->downloadTemplate(
            config('data_transfer.party_fields', []),
            'contact-import-template',
            $format
        );
    }

    /**
     * A pre-filled opening stock worksheet: one row per physical variant with
     * its identifiers and purchase price already populated, so the user only
     * needs to enter quantities. Warehouse is chosen in the wizard, so the
     * column is intentionally omitted.
     */
    public function downloadOpeningStockWorksheet(int $companyId, string $format = 'csv'): StreamedResponse
    {
        $headers = [
            'product_name', 'product_code', 'sku', 'barcode',
            'quantity', 'rate', 'batch_no', 'expiry_date', 'remarks',
        ];

        return $this->streamRows($headers, function (callable $emit) use ($companyId): void {
            Product::query()
                ->where('company_id', $companyId)
                ->physical()
                ->with(['variants:id,product_id,sku,barcode,purchase_price'])
                ->orderBy('name')
                ->lazyById(200)
                ->each(function (Product $product) use ($emit): void {
                    foreach ($product->variants as $variant) {
                        $emit([
                            $this->worksheetProductLabel($product->name, $variant),
                            $product->code,
                            $variant->sku,
                            $variant->barcode,
                            '',
                            (string) $variant->purchase_price,
                            '',
                            '',
                            '',
                        ]);
                    }
                });
        }, 'opening-stock-worksheet', $format);
    }

    private function worksheetProductLabel(?string $productName, ProductVariant $variant): string
    {
        $label = (string) $productName;

        return $variant->sku ? "{$label} ({$variant->sku})" : $label;
    }

    /**
     * @param  list<string>  $headers
     */
    private function downloadTemplate(array $headers, string $basename, string $format): StreamedResponse
    {
        $filename = $format === 'xlsx' ? "{$basename}.xlsx" : "{$basename}.csv";

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($headers) {
                $path = tempnam(sys_get_temp_dir(), 'tpl');
                $writer = new \App\Services\DataTransfer\Export\ExportWriter;
                $writer->open($path, 'xlsx');
                $writer->addRow($headers);
                $writer->addRow(array_fill(0, count($headers), ''));
                $writer->close();
                readfile($path);
                @unlink($path);
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($headers) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            fputcsv($out, array_fill(0, count($headers), ''));
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Stream a header row followed by rows emitted lazily by the producer.
     *
     * @param  list<string>  $headers
     * @param  callable(callable(list<string>): void): void  $rowProducer
     */
    private function streamRows(array $headers, callable $rowProducer, string $basename, string $format): StreamedResponse
    {
        $filename = $format === 'xlsx' ? "{$basename}.xlsx" : "{$basename}.csv";

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($headers, $rowProducer) {
                $path = tempnam(sys_get_temp_dir(), 'tpl');
                $writer = new ExportWriter;
                $writer->open($path, 'xlsx');
                $writer->addRow($headers);
                $rowProducer(fn (array $row) => $writer->addRow($row));
                $writer->close();
                readfile($path);
                @unlink($path);
            }, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
        }

        return response()->streamDownload(function () use ($headers, $rowProducer) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers);
            $rowProducer(function (array $row) use ($out) {
                fputcsv($out, $row);
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
