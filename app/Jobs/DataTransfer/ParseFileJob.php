<?php

namespace App\Jobs\DataTransfer;

use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\DataTransfer\FileParserService;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Jobs\DataTransfer\Concerns\SetsTenantFromDataTransferJob;

class ParseFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsTenantFromDataTransferJob;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public DataTransferJob $dataTransferJob,
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(FileParserService $parser): void
    {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        $job->update(['status' => DataTransferStatusEnum::Parsing]);

        $inspect = $parser->inspect($job->file_disk, $job->file_path);

        $suggested = $parser->suggestMapping(
            $inspect['headers'],
            config('data_transfer.product_fields', [])
        );

        $job->update([
            'status' => DataTransferStatusEnum::Parsed,
            'mapping' => $job->mapping ?: $suggested,
            'stats' => array_merge($job->stats ?? [], [
                'total_rows' => $inspect['total_rows'],
                'detected_headers' => $inspect['headers'],
            ]),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        $this->dataTransferJob->update([
            'status' => DataTransferStatusEnum::Failed,
            'error_summary' => $exception->getMessage(),
            'finished_at' => now(),
        ]);
    }
}
