<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use App\Enums\ProductTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $productType = $this->relationLoaded('product') && $this->product
            ? ($this->product->product_type instanceof ProductTypeEnum
                ? $this->product->product_type->value
                : (string) ($this->product->product_type ?? ''))
            : '';

        return [
            'id' => $this->id ?? '',
            'name' => $this->variant_name ?? '',
            'product_type' => $productType,
            'is_service' => $productType === ProductTypeEnum::SERVICE->value,
            $this->mergeWhen($this->whenLoaded('product'), function () {
                return [
                    'unit_id' => $this->product->unit_id ?? '',
                    'product_code' => $this->product->code ?? '',
                    'item_role' => $this->product->item_role?->value ?? '',
                    'item_role_label' => $this->product->item_role?->label() ?? '',
                    'is_saleable' => (bool) ($this->product->is_saleable ?? true),
                    'is_purchasable' => (bool) ($this->product->is_purchasable ?? true),
                ];
            }),
            'sku' => $this->sku ?? '',
            'barcode' => $this->barcode ?? '',
            'sales_price' => $this->sales_price ?? 0,
            'purchase_price' => $this->purchase_price ?? 0,
            'is_default' => $this->is_default ?? false,
            'is_serialized' => $this->is_serialized ?? false,
            'is_batch_tracked' => $this->is_batch_tracked ?? false,
            'stock' => $this->when(
                array_key_exists('stock_qty', $this->resource->getAttributes()),
                fn () => (float) ($this->stock_qty ?? 0),
            ),
            'total_stock' => $this->whenLoaded('stocks', fn () => (float) $this->stocks->sum('quantity')),
            'stocks_by_warehouse' => $this->whenLoaded('stocks', function () {
                return $this->stocks->map(fn ($s) => [
                    'warehouse_id' => $s->warehouse_id,
                    'warehouse_name' => $s->relationLoaded('warehouse') && $s->warehouse ? (string) $s->warehouse->name : '',
                    'quantity' => (float) $s->quantity,
                    'on_hold' => (float) ($s->on_hold ?? 0),
                ])->values();
            }),
            'variant_options' => $this->whenLoaded('variantOptions', function () {
                return $this->formatVariantOptions();
            }),
        ];
    }

    private function formatVariantOptions(): array
    {
        $list = [];

        foreach ($this->variantOptions ?? [] as $value) {
            $list[] = [
                'id' => $value->id,
                'attribute_id' => $value->attribute_id,
                'attribute_name' => $value->attribute->name ?? '',
                'value' => $value->value,
            ];
        }

        return $list;
    }
}
