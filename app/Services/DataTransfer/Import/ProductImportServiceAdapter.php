<?php

namespace App\Services\DataTransfer\Import;

use App\Models\DataTransferJob;
use App\Services\DataTransfer\ProductImportService;
use App\Services\DataTransfer\ProductImportLookupCache;

class ProductImportServiceAdapter implements ImportServiceInterface
{
    public function __construct(
        private ProductImportService $productImportService,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups): array
    {
        if (! $lookups instanceof ProductImportLookupCache) {
            throw new \InvalidArgumentException('Product import requires ProductImportLookupCache.');
        }

        $result = $this->productImportService->importRow($job, $normalized, $lookups);

        return [
            'action' => $result['action'],
            'entity_id' => $result['product_id'],
        ];
    }
}
