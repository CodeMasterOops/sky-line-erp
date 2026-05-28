<?php

namespace App\Services\DataTransfer;

use League\Csv\Writer;
use App\Models\DataTransferJob;
use App\Models\DataTransferRow;
use Illuminate\Support\Facades\Storage;

class ErrorReportGenerator
{
    public function generate(DataTransferJob $job): string
    {
        $disk = config('data_transfer.disk', 'local');
        $path = sprintf(
            'data-transfer/results/%d/%s-errors.csv',
            $job->company_id,
            $job->uuid
        );

        $stream = fopen('php://temp', 'r+');
        $writer = Writer::createFromStream($stream);
        $writer->insertOne(['row_number', 'status', 'errors', 'name', 'code', 'sku']);

        DataTransferRow::query()
            ->where('data_transfer_job_id', $job->id)
            ->whereIn('status', ['invalid', 'failed', 'skipped'])
            ->orderBy('row_number')
            ->chunk(500, function ($rows) use ($writer) {
                foreach ($rows as $row) {
                    $payload = $row->raw_payload ?? [];
                    $writer->insertOne([
                        $row->row_number,
                        $row->status->value ?? $row->status,
                        implode('; ', $row->errors ?? []),
                        $payload['name'] ?? '',
                        $payload['code'] ?? '',
                        $payload['sku'] ?? '',
                    ]);
                }
            });

        rewind($stream);
        Storage::disk($disk)->put($path, stream_get_contents($stream));
        fclose($stream);

        $job->update([
            'result_disk' => $disk,
            'result_path' => $path,
        ]);

        return $path;
    }
}
