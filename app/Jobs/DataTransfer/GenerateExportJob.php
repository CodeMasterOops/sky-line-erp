<?php

namespace App\Jobs\DataTransfer;

use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use App\Services\TenantService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\Export\ExportGeneratorFactory;
use App\Jobs\DataTransfer\Concerns\SetsTenantFromDataTransferJob;

class GenerateExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsTenantFromDataTransferJob;

    public int $tries = 3;

    public int $timeout = 3600;

    public function __construct(
        public DataTransferJob $dataTransferJob,
    ) {
        $queue = config('data_transfer.heavy_queue', 'data-transfer-heavy');
        $this->onQueue($queue);
    }

    public function handle(ExportGeneratorFactory $factory): void
    {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        try {
            $job->update([
                'status' => DataTransferStatusEnum::Processing,
                'started_at' => now(),
            ]);

            $format = $job->options['format'] ?? 'csv';
            $extension = $format === 'xlsx' ? 'xlsx' : 'csv';
            $disk = config('data_transfer.disk', 'local');
            $path = sprintf(
                'data-transfer/exports/%d/%s.%s',
                $job->company_id,
                $job->uuid,
                $extension
            );

            $absolutePath = Storage::disk($disk)->path($path);
            $dir = dirname($absolutePath);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $generator = $factory->make($job->entity_type);
            $generator->generate($job, $absolutePath);

            $job->update([
                'status' => DataTransferStatusEnum::Completed,
                'result_disk' => $disk,
                'result_path' => $path,
                'finished_at' => now(),
            ]);

            NotifyDataTransferCompleteJob::dispatch($job);
        } finally {
            TenantService::reset();
        }
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
