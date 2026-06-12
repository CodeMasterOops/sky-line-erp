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
use App\Services\DataTransfer\Import\ImportHandlerFactory;
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
        ImportHandlerFactory $importFactory,
    ): void {
        $job = $this->dataTransferJob->fresh();
        $this->setTenantFromJob($job);

        $job->update(['status' => DataTransferStatusEnum::Validating]);

        DataTransferRow::query()->where('data_transfer_job_id', $job->id)->delete();

        $mapping = $job->mapping ?? [];
        $headers = $job->stats['detected_headers'] ?? [];
        $lookups = $importFactory->lookups($job->entity_type, $job->company_id);
        $validator = $importFactory->validator($job->entity_type);
        $context = $this->buildContext($job);

        $mappedRows = [];
        $rowNumber = 0;

        foreach ($parser->iterateRows($job->file_disk, $job->file_path) as $index => $row) {
            if ($index === 0) {
                if ($headers === []) {
                    $headers = array_map(fn ($h) => trim((string) $h), $row);
                }

                continue;
            }

            $rowNumber++;
            $mappedRows[] = $parser->mapRow($headers, $row, $mapping);
        }

        if ($job->entity_type === DataTransferEntityTypeEnum::Warehouse && $lookups instanceof \App\Services\DataTransfer\WarehouseImportLookupCache) {
            $pendingKeys = [];
            foreach ($mappedRows as $mapped) {
                if (! empty($mapped['name'])) {
                    $pendingKeys[] = (string) $mapped['name'];
                }
                if (! empty($mapped['code'])) {
                    $pendingKeys[] = (string) $mapped['code'];
                }
            }
            $lookups->registerPendingKeys($pendingKeys);
        }

        $valid = 0;
        $invalid = 0;

        foreach ($mappedRows as $index => $mapped) {
            $result = $validator->validate($mapped, $lookups, $context);

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
                'row_number' => $index + 1,
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
                'total_rows' => count($mappedRows),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildContext(DataTransferJob $job): array
    {
        $context = [];

        if ($job->entity_type === DataTransferEntityTypeEnum::Party) {
            $context['default_party_type'] = $job->options['default_party_type'] ?? null;
        }

        return $context;
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
