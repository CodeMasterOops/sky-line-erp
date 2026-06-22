<?php

namespace App\Services\DataTransfer\Import;

use App\Models\DataTransferJob;

interface ImportServiceInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups): array;
}
