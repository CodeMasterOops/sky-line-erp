<?php

namespace App\Services\DataTransfer;

use App\Models\Warehouse;
use App\Enums\EntityCodeType;
use App\Models\DataTransferJob;
use Illuminate\Support\Facades\DB;
use App\Services\EntityCodeGenerator;
use App\Services\DataTransfer\Import\ImportServiceInterface;

class WarehouseImportService implements ImportServiceInterface
{
    public function __construct(
        private EntityCodeGenerator $codeGenerator,
    ) {}

    /**
     * @param  array<string, mixed>  $normalized
     * @return array{action: string, entity_id: int}
     */
    public function importRow(DataTransferJob $job, array $normalized, mixed $lookups): array
    {
        if (! $lookups instanceof WarehouseImportLookupCache) {
            throw new \InvalidArgumentException('Warehouse import requires WarehouseImportLookupCache.');
        }

        $duplicateMode = $job->options['duplicate_mode'] ?? 'update';
        $companyId = $job->company_id;

        $existing = $this->findExisting($companyId, $normalized);

        if ($existing && $duplicateMode === 'skip') {
            return ['action' => 'skipped', 'entity_id' => $existing->id];
        }

        if ($existing && $duplicateMode === 'create_only') {
            throw new \RuntimeException('Warehouse already exists.');
        }

        return DB::transaction(function () use ($job, $normalized, $existing, $companyId, $lookups) {
            $parentId = $this->resolveParentId($normalized, $lookups);

            $data = [
                'parent_id' => $parentId,
                'name' => $normalized['name'],
                'code' => $normalized['code'] ?? null,
                'phone' => $normalized['phone'] ?? null,
                'address' => $normalized['address'] ?? null,
            ];

            $action = 'imported';

            if ($existing) {
                if ($parentId !== null && $this->wouldCreateCircularReference($existing->id, $parentId)) {
                    throw new \RuntimeException('Invalid parent: would create a circular reference.');
                }

                $existing->update($data);
                $warehouse = $existing;
                $action = 'updated';
            } else {
                if (blank($data['code'])) {
                    $data['code'] = $this->codeGenerator->generateForType(
                        EntityCodeType::Warehouse,
                        $companyId,
                    );
                }

                $data['company_id'] = $companyId;
                $data['import_batch_id'] = $job->batch_id;
                $warehouse = Warehouse::create($data);
            }

            $lookups->register($warehouse);

            return [
                'action' => $action,
                'entity_id' => $warehouse->id,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function findExisting(int $companyId, array $normalized): ?Warehouse
    {
        if (! empty($normalized['code'])) {
            $byCode = Warehouse::query()
                ->where('company_id', $companyId)
                ->where('code', $normalized['code'])
                ->first();

            if ($byCode) {
                return $byCode;
            }
        }

        if (! empty($normalized['name'])) {
            return Warehouse::query()
                ->where('company_id', $companyId)
                ->where('name', $normalized['name'])
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function resolveParentId(array $normalized, WarehouseImportLookupCache $lookups): ?int
    {
        if (! empty($normalized['parent_id'])) {
            return (int) $normalized['parent_id'];
        }

        $parentKey = $normalized['parent_key'] ?? null;
        if ($parentKey === null) {
            return null;
        }

        $resolved = $lookups->resolve($parentKey);
        if ($resolved === null || $resolved === -1) {
            throw new \RuntimeException('Parent warehouse not found.');
        }

        return $resolved;
    }

    private function wouldCreateCircularReference(int $warehouseId, int $parentId): bool
    {
        if ($parentId === $warehouseId) {
            return true;
        }

        $currentId = $parentId;
        $guard = 0;

        while ($currentId !== null && $guard++ < 1000) {
            if ($currentId === $warehouseId) {
                return true;
            }

            $row = Warehouse::query()->whereKey($currentId)->first(['id', 'parent_id']);
            $currentId = $row && $row->parent_id !== null ? (int) $row->parent_id : null;
        }

        return false;
    }
}
