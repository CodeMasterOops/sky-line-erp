<?php

namespace App\Services\DataTransfer;

use App\Models\Tax;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Attribute;
use App\Models\Warehouse;
use App\Models\ProductCategory;
use Illuminate\Support\Facades\Cache;

class ProductImportLookupCache
{
    /** @var array<string, int> */
    private array $categoriesByKey = [];

    /** @var array<string, int> */
    private array $unitsByKey = [];

    /** @var array<string, int> */
    private array $brandsByKey = [];

    /** @var array<string, int> */
    private array $taxesByKey = [];

    /** @var array<string, int> */
    private array $warehousesByKey = [];

    /** @var array<string, array<string, int>> */
    private array $attributeValuesByKey = [];

    public function __construct(private int $companyId) {}

    public static function forCompany(int $companyId): self
    {
        return Cache::remember(
            "dt:lookups:{$companyId}",
            300,
            fn () => (new self($companyId))->warm()
        );
    }

    public static function forget(int $companyId): void
    {
        Cache::forget("dt:lookups:{$companyId}");
    }

    public function warm(): self
    {
        ProductCategory::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name'])
            ->each(fn ($c) => $this->categoriesByKey[strtolower($c->name)] = $c->id);

        Unit::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function ($u) {
                $this->unitsByKey[strtolower($u->name)] = $u->id;
                if ($u->code) {
                    $this->unitsByKey[strtolower($u->code)] = $u->id;
                }
            });

        Brand::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function ($b) {
                $this->brandsByKey[strtolower($b->name)] = $b->id;
                if ($b->code) {
                    $this->brandsByKey[strtolower($b->code)] = $b->id;
                }
            });

        Tax::query()
            ->lineItem()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'rate'])
            ->each(function ($t) {
                $this->taxesByKey[strtolower($t->name)] = $t->id;
                $this->taxesByKey[(string) $t->rate] = $t->id;
            });

        Warehouse::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function ($w) {
                $this->warehousesByKey[strtolower($w->name)] = $w->id;
                if ($w->code) {
                    $this->warehousesByKey[strtolower($w->code)] = $w->id;
                }
            });

        Attribute::query()
            ->where('company_id', $this->companyId)
            ->with('values')
            ->get()
            ->each(function (Attribute $attr) {
                foreach ($attr->values as $value) {
                    $key = strtolower($attr->name).'|'.strtolower($value->value);
                    $this->attributeValuesByKey[$key] = $value->id;
                }
            });

        return $this;
    }

    public function resolveCategory(?string $value): ?int
    {
        return $value ? ($this->categoriesByKey[strtolower($value)] ?? null) : null;
    }

    public function resolveUnit(?string $value): ?int
    {
        return $value ? ($this->unitsByKey[strtolower($value)] ?? null) : null;
    }

    public function resolveBrand(?string $value): ?int
    {
        return $value ? ($this->brandsByKey[strtolower($value)] ?? null) : null;
    }

    public function resolveTax(?string $value): ?int
    {
        return $value ? ($this->taxesByKey[strtolower($value)] ?? null) : null;
    }

    public function resolveWarehouse(?string $value): ?int
    {
        return $value ? ($this->warehousesByKey[strtolower($value)] ?? null) : null;
    }

    public function resolveAttributeValue(?string $attrName, ?string $attrValue): ?int
    {
        if (! $attrName || ! $attrValue) {
            return null;
        }

        return $this->attributeValuesByKey[strtolower($attrName).'|'.strtolower($attrValue)] ?? null;
    }
}
