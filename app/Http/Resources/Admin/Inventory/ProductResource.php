<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Admin\Settings\TaxResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stock = $this->aggregatedStock($request);

        $row = [
            'id' => $this->id ?? '',
            'product_category_id' => $this->product_category_id ?? '',
            'product_type' => $this->product_type ?? '',
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'image' => $this->image ?? '',
            'unit_id' => $this->unit_id ?? '',
            'brand_id' => $this->brand_id ?? '',
            'tax_id' => $this->tax_id ?? '',
            'tax' => $this->when(
                $this->relationLoaded('tax') && $this->tax,
                fn () => TaxResource::make($this->tax)
            ),
            'has_variants' => (bool) ($this->has_variants ?? false),
            'reorder_quantity' => $this->reorder_quantity ?? 0,
            'description' => $this->description ?? '',
            'category' => $this->productCategory ? $this->productCategory->name : '',
            'brand' => $this->brand ? $this->brand->name : '',
            'unit' => $this->unit ? $this->unit->name : '',
            'total_stock' => $stock['total_stock'],
            'stock_by_warehouse' => $stock['stock_by_warehouse'],
            'defaultVariant' => ProductVariantResource::make($this->defaultVariant),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];

        if ($request->boolean('include_inventory_value')) {
            $row['total_inventory_value'] = $stock['total_inventory_value'];
        }

        return $row;
    }

    /**
     * @return array{total_stock: int, stock_by_warehouse: list<array<string, mixed>>, total_inventory_value: float}
     */
    private function aggregatedStock(?Request $request): array
    {
        if (! $this->resource->relationLoaded('variants')) {
            return [
                'total_stock' => 0,
                'stock_by_warehouse' => [],
                'total_inventory_value' => 0.0,
            ];
        }

        $includeValuation = $request && $request->boolean('include_inventory_value');

        $total = 0;
        $byWarehouse = [];
        $layerValueAdded = [];

        foreach ($this->resource->variants as $variant) {
            if (! $variant->relationLoaded('stocks')) {
                continue;
            }

            foreach ($variant->stocks as $stock) {
                $qty = (int) $stock->quantity;
                $total += $qty;
                $wid = (int) $stock->warehouse_id;

                if (! isset($byWarehouse[$wid])) {
                    $byWarehouse[$wid] = [
                        'warehouse_id' => $wid,
                        'warehouse_name' => $stock->relationLoaded('warehouse') && $stock->warehouse
                            ? (string) $stock->warehouse->name
                            : '',
                        'quantity' => 0,
                    ];
                }

                $byWarehouse[$wid]['quantity'] += $qty;

                $layerKey = $variant->id.'-'.$wid;
                if (
                    $includeValuation
                    && $variant->relationLoaded('stockLayers')
                    && ! isset($layerValueAdded[$layerKey])
                ) {
                    $layerValueAdded[$layerKey] = true;
                    $layerValue = (float) $variant->stockLayers
                        ->where('warehouse_id', $wid)
                        ->where('qty_remaining', '>', 0)
                        ->sum(fn ($layer) => $layer->qty_remaining * $layer->unit_cost);
                    $byWarehouse[$wid]['inventory_value'] = round(
                        ($byWarehouse[$wid]['inventory_value'] ?? 0) + $layerValue,
                        2
                    );
                }
            }
        }

        $rows = array_values($byWarehouse);
        $totalInventoryValue = 0.0;

        if ($includeValuation) {
            foreach ($rows as &$row) {
                $row['inventory_value'] = round((float) ($row['inventory_value'] ?? 0), 2);
                $totalInventoryValue += $row['inventory_value'];
            }
            unset($row);
            $totalInventoryValue = round($totalInventoryValue, 2);
        }

        return [
            'total_stock' => $total,
            'stock_by_warehouse' => $rows,
            'total_inventory_value' => $totalInventoryValue,
        ];
    }
}
