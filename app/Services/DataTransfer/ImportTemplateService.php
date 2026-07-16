<?php

namespace App\Services\DataTransfer;

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
}
