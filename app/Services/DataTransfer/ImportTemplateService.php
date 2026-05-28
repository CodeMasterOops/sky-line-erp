<?php

namespace App\Services\DataTransfer;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportTemplateService
{
    public function downloadProductTemplate(string $format = 'csv'): StreamedResponse
    {
        $headers = config('data_transfer.product_fields', []);
        $filename = $format === 'xlsx' ? 'product-import-template.xlsx' : 'product-import-template.csv';

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
