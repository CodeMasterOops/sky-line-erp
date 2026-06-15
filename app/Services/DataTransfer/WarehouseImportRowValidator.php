<?php

namespace App\Services\DataTransfer;

use App\Services\DataTransfer\Import\ImportRowValidatorInterface;

class WarehouseImportRowValidator implements ImportRowValidatorInterface
{
    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $context
     * @return array{normalized: array<string, mixed>, errors: list<string>}
     */
    public function validate(array $row, mixed $lookups, array $context = []): array
    {
        if (! $lookups instanceof WarehouseImportLookupCache) {
            return [
                'normalized' => $row,
                'errors' => ['Invalid lookup cache for warehouse import.'],
            ];
        }

        $errors = [];

        if (empty($row['name'])) {
            $errors[] = 'Warehouse name is required.';
        }

        $parentId = null;
        $parentValue = $row['parent'] ?? null;
        if ($parentValue !== null && $parentValue !== '') {
            $parentId = $lookups->resolve($parentValue);
            if ($parentId === -1) {
                $parentId = null;
            } elseif ($parentId === null && ! $lookups->isPending($parentValue)) {
                $errors[] = 'Parent warehouse not found.';
            }
        }

        $normalized = [
            'name' => $row['name'] ?? null,
            'code' => $row['code'] ?? null,
            'parent_id' => $parentId,
            'parent_key' => ($parentValue !== null && $parentValue !== '') ? strtolower(trim((string) $parentValue)) : null,
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
        ];

        return [
            'normalized' => $normalized,
            'errors' => $errors,
        ];
    }
}
