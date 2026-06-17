<?php

namespace App\Jobs\DataTransfer;

use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use App\Models\DataTransferRow;
use App\Services\TenantService;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Services\DataTransfer\ErrorReportGenerator;
use App\Enums\DataTransfer\DataTransferRowStatusEnum;
use App\Services\DataTransfer\Import\ImportHandlerFactory;
use App\Jobs\DataTransfer\Concerns\SetsTenantFromDataTransferJob;

class ProcessImportChunkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsTenantFromDataTransferJob;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public DataTransferJob $dataTransferJob,
        public int $offset = 0,
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(
        ImportHandlerFactory $importFactory,
        ErrorReportGenerator $errorReport,
    ): void {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        try {
            if ($job->status === DataTransferStatusEnum::Cancelled) {
                return;
            }

            if ($this->offset === 0) {
                $job->update([
                    'status' => DataTransferStatusEnum::Processing,
                    'started_at' => $job->started_at ?? now(),
                ]);
            }

            $chunkSize = (int) config('data_transfer.chunk_size', 100);
            $lookups = $importFactory->lookups($job->entity_type, $job->company_id);
            $importService = $importFactory->service($job->entity_type);
            $targetType = $importFactory->targetModelClass($job->entity_type);

            $rows = DataTransferRow::query()
                ->where('data_transfer_job_id', $job->id)
                ->where('status', DataTransferRowStatusEnum::Valid)
                ->orderBy('row_number')
                ->skip($this->offset)
                ->take($chunkSize)
                ->get();

            if ($rows->isEmpty()) {
                $this->finalize($job, $errorReport);

                return;
            }

            foreach ($rows as $row) {
                try {
                    $result = $importService->importRow(
                        $job,
                        $row->normalized_payload ?? [],
                        $lookups,
                    );

                    $status = match ($result['action']) {
                        'skipped' => DataTransferRowStatusEnum::Skipped,
                        'updated' => DataTransferRowStatusEnum::Updated,
                        default => DataTransferRowStatusEnum::Imported,
                    };

                    $row->update([
                        'status' => $status,
                        'target_type' => $targetType,
                        'target_id' => $result['entity_id'],
                        'errors' => null,
                    ]);

                    $job->incrementStat(match ($result['action']) {
                        'skipped' => 'skipped',
                        'updated' => 'updated',
                        default => 'created',
                    });
                } catch (\Throwable $e) {
                    $row->update([
                        'status' => DataTransferRowStatusEnum::Failed,
                        'errors' => [$e->getMessage()],
                    ]);
                    $job->incrementStat('failed');
                }

                $job->incrementStat('processed');
            }

            if ($rows->count() < $chunkSize) {
                $this->finalize($job->fresh(), $errorReport);
            } else {
                self::dispatch($job, $this->offset + $chunkSize);
            }
        } finally {
            TenantService::reset();
        }
    }

    private function finalize(DataTransferJob $job, ErrorReportGenerator $errorReport): void
    {
        $stats = $job->fresh()->stats ?? [];
        $failed = (int) ($stats['failed'] ?? 0);
        $invalid = (int) ($stats['invalid'] ?? 0);

        if ($failed > 0 || $invalid > 0) {
            $errorReport->generate($job);
        }

        $job->update([
            'status' => ($failed > 0 || $invalid > 0)
                ? DataTransferStatusEnum::CompletedWithErrors
                : DataTransferStatusEnum::Completed,
            'finished_at' => now(),
        ]);

        NotifyDataTransferCompleteJob::dispatch($job);
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
