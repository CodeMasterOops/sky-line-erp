<?php

namespace App\Services\DataTransfer\Import;

use App\Models\DataTransferJob;

interface ImportRowValidatorInterface
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{
     *     normalized: array<string, mixed>,
     *     errors: list<string>,
     *     field_errors?: list<array{field: string, value: string, message: string, suggestions: list<array{id: int, label: string}>}>,
     *     skip?: bool
     * }
     */
    public function validate(array $row, mixed $lookups, array $context = []): array;
}

interface ImportServiceInterface
{
    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups): array;
}
