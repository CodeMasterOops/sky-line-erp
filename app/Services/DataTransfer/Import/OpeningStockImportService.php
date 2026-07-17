<?php

namespace App\Services\DataTransfer\Import;

use App\Models\DataTransferJob;
use App\Services\Inventory\OpeningStockEntryService;
use App\Services\DataTransfer\OpeningStockImportLookupCache;

class OpeningStockImportService implements ImportServiceInterface
{
    public function __construct(
        private OpeningStockEntryService $openingStockEntryService,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups): array
    {
        if (! $lookups instanceof OpeningStockImportLookupCache) {
            throw new \InvalidArgumentException('Opening stock import requires OpeningStockImportLookupCache.');
        }

        if (! $job->branch_id) {
            throw new \RuntimeException('Branch context is required for opening stock import.');
        }

        if (! $job->user_id) {
            throw new \RuntimeException('User context is required for opening stock import.');
        }

        $warehouseId = (int) $normalized['warehouse_id'];
        $referenceNo = "OSIMP-{$job->batch_id}-{$warehouseId}";

        $result = $this->openingStockEntryService->applyImportLine(
            $job->company_id,
            $job->branch_id,
            $referenceNo,
            (int) $normalized['product_variant_id'],
            $normalized['unit_id'] !== null ? (int) $normalized['unit_id'] : null,
            $warehouseId,
            (float) $normalized['quantity'],
            (float) $normalized['unit_cost'],
            (int) $job->user_id,
            $normalized['batch_no'] ?? null,
            $normalized['expiry_date'] ?? null,
            $normalized['remarks'] ?? null,
        );

        return [
            'action' => $result['action'],
            'entity_id' => $result['entry_id'],
        ];
    }
}
