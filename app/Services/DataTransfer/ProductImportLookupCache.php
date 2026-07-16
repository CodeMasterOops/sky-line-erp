<?php

namespace App\Services\DataTransfer;

use App\Models\Tax;
use App\Models\Unit;
use App\Models\Brand;
use App\Models\Attribute;
use App\Models\ProductCategory;
use App\Models\ImportValueAlias;
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

    /** @var array<string, array<string, int>> */
    private array $attributeValuesByKey = [];

    /**
     * Label lists per field for fuzzy suggestion scanning.
     *
     * @var array<string, list<array{id: int, label: string}>>
     */
    private array $labelsByField = [
        'category' => [],
        'unit' => [],
        'brand' => [],
        'tax' => [],
    ];

    /**
     * Lowercased source values the user chose to skip, keyed by field.
     *
     * @var array<string, array<string, true>>
     */
    private array $skipKeysByField = [];

    /**
     * Resolved product_type aliases (lowercased source => 'product'|'service').
     *
     * @var array<string, string>
     */
    private array $productTypeAliases = [];

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
        $categories = ProductCategory::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'parent_id']);

        /** @var array<int, ProductCategory> $byId */
        $byId = $categories->keyBy('id')->all();
        $parentIds = $categories->pluck('parent_id')->filter()->unique();

        foreach ($categories as $category) {
            $fullPath = ProductCategory::buildFullPathFromMap($category, $byId);

            if (! $parentIds->contains($category->id)) {
                $this->categoriesByKey[strtolower($fullPath)] = $category->id;
                $this->categoriesByKey[strtolower($category->name)] = $category->id;
            }

            // Parents stay in the suggestion pool so typos near a parent surface a hint.
            $this->labelsByField['category'][] = ['id' => $category->id, 'label' => $fullPath];
        }

        Unit::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function ($u) {
                $this->unitsByKey[strtolower($u->name)] = $u->id;
                if ($u->code) {
                    $this->unitsByKey[strtolower($u->code)] = $u->id;
                }
                $this->labelsByField['unit'][] = ['id' => $u->id, 'label' => $u->name];
            });

        Brand::query()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'code'])
            ->each(function ($b) {
                $this->brandsByKey[strtolower($b->name)] = $b->id;
                if ($b->code) {
                    $this->brandsByKey[strtolower($b->code)] = $b->id;
                }
                $this->labelsByField['brand'][] = ['id' => $b->id, 'label' => $b->name];
            });

        Tax::query()
            ->lineItem()
            ->where('company_id', $this->companyId)
            ->get(['id', 'name', 'rate'])
            ->each(function ($t) {
                $this->taxesByKey[strtolower($t->name)] = $t->id;
                foreach ($this->rateKeys((float) $t->rate) as $rateKey) {
                    $this->taxesByKey[$rateKey] = $t->id;
                }
                $this->labelsByField['tax'][] = ['id' => $t->id, 'label' => $t->name];
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

        $this->loadAliases();

        return $this;
    }

    private function loadAliases(): void
    {
        ImportValueAlias::query()
            ->where('company_id', $this->companyId)
            ->where('entity_type', 'product')
            ->get()
            ->each(function (ImportValueAlias $alias) {
                $source = strtolower(trim($alias->source_value));

                if ($alias->field === 'product_type') {
                    if ($alias->target_value !== null) {
                        $this->productTypeAliases[$source] = strtolower($alias->target_value);
                    }

                    return;
                }

                if ($alias->target_id === null) {
                    $this->skipKeysByField[$alias->field][$source] = true;

                    return;
                }

                match ($alias->field) {
                    'category' => $this->categoriesByKey[$source] = $alias->target_id,
                    'unit' => $this->unitsByKey[$source] = $alias->target_id,
                    'brand' => $this->brandsByKey[$source] = $alias->target_id,
                    'tax' => $this->taxesByKey[$source] = $alias->target_id,
                    default => null,
                };
            });
    }

    /**
     * Generic field match with alias resolution and fuzzy suggestions.
     */
    public function match(string $field, ?string $value): ValueMatch
    {
        $normalized = strtolower(trim((string) $value));

        if ($normalized === '') {
            return ValueMatch::unmatched();
        }

        if (isset($this->skipKeysByField[$field][$normalized])) {
            return ValueMatch::skip();
        }

        $map = match ($field) {
            'category' => $this->categoriesByKey,
            'unit' => $this->unitsByKey,
            'brand' => $this->brandsByKey,
            'tax' => $this->taxesByKey,
            default => [],
        };

        if (isset($map[$normalized])) {
            return ValueMatch::matched($map[$normalized]);
        }

        $suggestions = $this->suggest($field, $normalized);

        return $suggestions === [] ? ValueMatch::unmatched() : ValueMatch::suggested($suggestions);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    public function suggest(string $field, string $value, int $limit = 3): array
    {
        $labels = $this->labelsByField[$field] ?? [];
        if ($labels === []) {
            return [];
        }

        $value = strtolower(trim($value));
        $scored = [];

        foreach ($labels as $entry) {
            $candidate = strtolower($entry['label']);
            $leaf = strtolower((string) (strrchr($candidate, '>') ?: $candidate));
            $leaf = ltrim($leaf, '> ');

            similar_text($value, $candidate, $percentFull);
            similar_text($value, $leaf, $percentLeaf);
            $percent = max($percentFull, $percentLeaf);

            if (str_contains($candidate, $value) || str_contains($value, $leaf)) {
                $percent = max($percent, 80.0);
            }

            if ($percent >= 60.0) {
                $scored[] = ['id' => $entry['id'], 'label' => $entry['label'], 'score' => $percent];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_map(
            fn ($s) => ['id' => $s['id'], 'label' => $s['label']],
            array_slice($scored, 0, $limit)
        );
    }

    public function resolveProductTypeAlias(string $value): ?string
    {
        return $this->productTypeAliases[strtolower(trim($value))] ?? null;
    }

    /**
     * All known labels for the resolvable lookup fields, sorted by label.
     *
     * @return array<string, list<array{id: int, label: string}>>
     */
    public function resolvableLabels(): array
    {
        $result = [];

        foreach (['category', 'unit', 'brand', 'tax'] as $field) {
            $labels = $this->labelsByField[$field] ?? [];
            usort($labels, fn ($a, $b) => strcasecmp($a['label'], $b['label']));
            $result[$field] = array_values($labels);
        }

        return $result;
    }

    public function resolveCategory(?string $value): ?int
    {
        $match = $this->match('category', $value);

        return $match->isMatched() ? $match->id : null;
    }

    public function resolveUnit(?string $value): ?int
    {
        $match = $this->match('unit', $value);

        return $match->isMatched() ? $match->id : null;
    }

    public function resolveBrand(?string $value): ?int
    {
        $match = $this->match('brand', $value);

        return $match->isMatched() ? $match->id : null;
    }

    public function resolveTax(?string $value): ?int
    {
        $match = $this->match('tax', $value);

        return $match->isMatched() ? $match->id : null;
    }

    public function resolveAttributeValue(?string $attrName, ?string $attrValue): ?int
    {
        if (! $attrName || ! $attrValue) {
            return null;
        }

        return $this->attributeValuesByKey[strtolower(trim($attrName)).'|'.strtolower(trim($attrValue))] ?? null;
    }

    /**
     * Tax rates may be typed as "10", "10.0", "10.00" or "10%"; index all forms.
     *
     * @return list<string>
     */
    private function rateKeys(float $rate): array
    {
        $keys = [
            (string) $rate,
            number_format($rate, 2, '.', ''),
            rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.'),
        ];

        return array_values(array_unique(array_map(fn ($k) => strtolower($k), $keys)));
    }
}
