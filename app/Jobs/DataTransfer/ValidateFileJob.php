<?php

namespace App\Jobs\DataTransfer;

use Illuminate\Bus\Queueable;
use App\Models\DataTransferJob;
use App\Models\DataTransferRow;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\DataTransfer\FileParserService;
use App\Enums\DataTransfer\DataTransferStatusEnum;
use App\Enums\DataTransfer\DataTransferRowStatusEnum;
use App\Enums\DataTransfer\DataTransferEntityTypeEnum;
use App\Services\DataTransfer\ProductImportLookupCache;
use App\Services\DataTransfer\ProductImportRowValidator;
use App\Jobs\DataTransfer\Concerns\SetsTenantFromDataTransferJob;

class ValidateFileJob implements ShouldQueue
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
    ) {
        $this->onQueue(config('data_transfer.queue', 'data-transfer'));
    }

    public function handle(
        FileParserService $parser,
        ProductImportRowValidator $rowValidator,
    ): void {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        $job->update(['status' => DataTransferStatusEnum::Validating]);

        DataTransferRow::query()->where('data_transfer_job_id', $job->id)->delete();

        $mapping = $job->mapping ?? [];
        $headers = $job->stats['detected_headers'] ?? [];
        $lookups = ProductImportLookupCache::forCompany($job->company_id);

        $valid = 0;
        $invalid = 0;
        $rowNumber = 0;

        foreach ($parser->iterateRows($job->file_disk, $job->file_path) as $index => $row) {
            if ($index === 0) {
                if ($headers === []) {
                    $headers = array_map(fn ($h) => trim((string) $h), $row);
                }

                continue;
            }

            $rowNumber++;
            $mapped = $parser->mapRow($headers, $row, $mapping);

            if ($job->entity_type === DataTransferEntityTypeEnum::Product) {
                $result = $rowValidator->validate($mapped, $lookups);
            } else {
                $result = ['normalized' => $mapped, 'errors' => ['Unsupported entity type.']];
            }

            $status = $result['errors'] === []
                ? DataTransferRowStatusEnum::Valid
                : DataTransferRowStatusEnum::Invalid;

            if ($status === DataTransferRowStatusEnum::Valid) {
                $valid++;
            } else {
                $invalid++;
            }

            DataTransferRow::query()->create([
                'data_transfer_job_id' => $job->id,
                'row_number' => $rowNumber,
                'status' => $status,
                'raw_payload' => $mapped,
                'normalized_payload' => $result['normalized'],
                'errors' => $result['errors'],
            ]);
        }

        $job->update([
            'status' => DataTransferStatusEnum::Validated,
            'stats' => array_merge($job->stats ?? [], [
                'valid' => $valid,
                'invalid' => $invalid,
                'total_rows' => $rowNumber,
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
