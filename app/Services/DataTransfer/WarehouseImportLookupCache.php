<?php

namespace App\Services\DataTransfer;

use App\Models\Warehouse;
use Illuminate\Support\Facades\Cache;

class WarehouseImportLookupCache
{
    /** @var array<string, int> */
    private array $warehousesByKey = [];

    public function __construct(private int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return Cache::remember(
            "dt:warehouse-lookups:{$companyId}",
            300,
            fn () => (new self($companyId))->warm()
        );
    }

    public static function forget(int $companyId): void
    {
        Cache::forget("dt:warehouse-lookups:{$companyId}");
    }

    public function warm(): self
    {
        Warehouse::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function (Warehouse $warehouse): void {
                $this->register($warehouse);
            });

        return $this;
    }

    public function register(Warehouse $warehouse): void
    {
        $this->warehousesByKey[strtolower($warehouse->name)] = $warehouse->id;
        if ($warehouse->code) {
            $this->warehousesByKey[strtolower($warehouse->code)] = $warehouse->id;
        }
    }

    public function resolve(?string $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->warehousesByKey[strtolower($value)] ?? null;
    }

    /**
     * @param  list<string>  $keys
     */
    public function registerPendingKeys(array $keys): void
    {
        foreach ($keys as $key) {
            $normalized = strtolower(trim($key));
            if ($normalized !== '' && ! isset($this->warehousesByKey[$normalized])) {
                $this->warehousesByKey[$normalized] = -1;
            }
        }
    }

    public function isPending(?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        return ($this->warehousesByKey[strtolower($value)] ?? null) === -1;
    }
}
