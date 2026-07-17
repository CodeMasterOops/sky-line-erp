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

        if ($job->entity_type === DataTransferEntityTypeEnum::Product) {
            \App\Services\DataTransfer\ProductImportLookupCache::forget($job->company_id);
        }

        if ($job->entity_type === DataTransferEntityTypeEnum::Party) {
            \App\Services\DataTransfer\PartyImportLookupCache::forget($job->company_id);
        }

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
        $skipped = 0;

        /** @var array<string, int> $seenCodes */
        $seenCodes = [];
        /** @var array<string, array{value: string, count: int, suggestions: list<array{id: int, label: string}>}> $unresolved */
        $unresolved = [];

        foreach ($mappedRows as $index => $mapped) {
            $result = $validator->validate($mapped, $lookups, $context);

            $errors = $result['errors'];
            $fieldErrors = $result['field_errors'] ?? [];
            $rowSkip = $result['skip'] ?? false;

            $code = trim((string) ($mapped['code'] ?? ''));
            if ($code !== '') {
                $codeKey = strtolower($code);
                if (isset($seenCodes[$codeKey])) {
                    $errors[] = "Duplicate code '{$code}' within file (first seen at row {$seenCodes[$codeKey]}).";
                } else {
                    $seenCodes[$codeKey] = $index + 1;
                }
            }

            if ($rowSkip && $errors === []) {
                $status = DataTransferRowStatusEnum::Skipped;
                $skipped++;
            } elseif ($errors === []) {
                $status = DataTransferRowStatusEnum::Valid;
                $valid++;
            } else {
                $status = DataTransferRowStatusEnum::Invalid;
                $invalid++;
            }

            foreach ($fieldErrors as $fieldError) {
                $key = $fieldError['field'].'|'.strtolower($fieldError['value']);
                if (isset($unresolved[$key])) {
                    $unresolved[$key]['count']++;
                } else {
                    $unresolved[$key] = [
                        'field' => $fieldError['field'],
                        'value' => $fieldError['value'],
                        'count' => 1,
                        'suggestions' => $fieldError['suggestions'],
                    ];
                }
            }

            DataTransferRow::query()->create([
                'data_transfer_job_id' => $job->id,
                'row_number' => $index + 1,
                'status' => $status,
                'raw_payload' => $mapped,
                'normalized_payload' => $result['normalized'],
                'errors' => $errors,
                'field_errors' => $fieldErrors,
            ]);
        }

        $job->update([
            'status' => DataTransferStatusEnum::Validated,
            'stats' => array_merge($job->stats ?? [], [
                'valid' => $valid,
                'invalid' => $invalid,
                'skipped' => $skipped,
                'total_rows' => count($mappedRows),
                'unresolved' => array_values($unresolved),
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

        if ($job->entity_type === DataTransferEntityTypeEnum::OpeningStock) {
            $context['default_warehouse_id'] = $job->options['warehouse_id'] ?? null;
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
