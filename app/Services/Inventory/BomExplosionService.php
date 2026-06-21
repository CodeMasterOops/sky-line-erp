<?php

namespace App\Services\Inventory;

use App\Models\Bom;
use App\Models\BomItem;

/**
 * Read-only multi-level BOM tooling:
 *  - explode(): recursively expand a BOM into its component tree, recursing into any
 *    component that is itself manufactured (has an active BOM), and aggregating the
 *    leaf raw-material requirements for a given output quantity.
 *  - whereUsed(): reverse lookup — which BOMs consume a given variant.
 */
class BomExplosionService
{
    private const MAX_DEPTH = 20;

    private const CONVERSION_ITEM_TYPES = ['labour', 'overhead'];

    /**
     * @return array{tree: list<array<string, mixed>>, materials: list<array<string, mixed>>}
     */
    public function explode(Bom $bom, float $qty): array
    {
        $materials = [];
        $tree = $this->buildNodes($bom, $qty, [], 0, $materials);

        return [
            'tree' => $tree,
            'materials' => array_values($materials),
        ];
    }

    /**
     * @param  array<int, bool>  $path  variant ids already in the current branch (cycle guard)
     * @param  array<int, array<string, mixed>>  $materials  aggregated leaf requirements (by variant id), by reference
     * @return list<array<string, mixed>>
     */
    private function buildNodes(Bom $bom, float $qty, array $path, int $depth, array &$materials): array
    {
        $bom->loadMissing('items.productVariant.product');
        $outputQty = (float) $bom->output_qty ?: 1.0;
        $ratio = $qty / $outputQty;

        $nodes = [];

        foreach ($bom->items as $item) {
            $requiredQty = round($item->effective_qty * $ratio, 4);
            $variant = $item->productVariant;
            $product = $variant?->product;

            $node = [
                'variant_id' => $item->product_variant_id,
                'name' => $product?->name ?? 'Variant #'.$item->product_variant_id,
                'sku' => $variant?->sku ?? '',
                'code' => $product?->code ?? '',
                'item_type' => $item->item_type,
                'qty' => $requiredQty,
                'unit_id' => $item->unit_id,
                'is_sub_assembly' => false,
                'bom_id' => null,
                'children' => [],
            ];

            if (in_array($item->item_type, self::CONVERSION_ITEM_TYPES, true)) {
                $nodes[] = $node;

                continue;
            }

            $childBom = $depth < self::MAX_DEPTH && empty($path[$item->product_variant_id])
                ? $this->defaultBomFor($item->product_variant_id)
                : null;

            if ($childBom) {
                $node['is_sub_assembly'] = true;
                $node['bom_id'] = $childBom->id;
                $node['children'] = $this->buildNodes(
                    $childBom,
                    $requiredQty,
                    $path + [$item->product_variant_id => true],
                    $depth + 1,
                    $materials,
                );
            } else {
                $this->accumulateMaterial($materials, $node);
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    /**
     * @param  array<int, array<string, mixed>>  $materials
     * @param  array<string, mixed>  $node
     */
    private function accumulateMaterial(array &$materials, array $node): void
    {
        $id = $node['variant_id'];

        if (! isset($materials[$id])) {
            $materials[$id] = [
                'variant_id' => $id,
                'name' => $node['name'],
                'sku' => $node['sku'],
                'code' => $node['code'],
                'total_qty' => 0.0,
            ];
        }

        $materials[$id]['total_qty'] = round($materials[$id]['total_qty'] + $node['qty'], 4);
    }

    public function defaultBomFor(int $variantId): ?Bom
    {
        return Bom::query()
            ->where('product_variant_id', $variantId)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * BOMs that consume the given variant as a component (direct, one level).
     *
     * @return list<array<string, mixed>>
     */
    public function whereUsed(int $variantId): array
    {
        return BomItem::query()
            ->where('product_variant_id', $variantId)
            ->with(['bom' => fn ($q) => $q->with('productVariant.product')])
            ->get()
            ->filter(fn (BomItem $item) => $item->bom !== null)
            ->map(fn (BomItem $item) => [
                'bom_id' => $item->bom->id,
                'bom_name' => $item->bom->name,
                'bom_version' => $item->bom->version,
                'is_active' => (bool) $item->bom->is_active,
                'output_variant_id' => $item->bom->product_variant_id,
                'output_name' => $item->bom->productVariant?->product?->name ?? '',
                'output_sku' => $item->bom->productVariant?->sku ?? '',
                'quantity' => (float) $item->quantity,
                'item_type' => $item->item_type,
            ])
            ->values()
            ->all();
    }
}
