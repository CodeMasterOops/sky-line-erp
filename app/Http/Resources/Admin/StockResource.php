<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'thumbnail_image_url' => $this->thumbnail_image_url ?? '',
            'has_variants' => $this->has_variants ?? false,
            'is_active' => $this->is_active ?? true,
            'variants' => $this->variantList(),
        ];
    }

    private function variantList(): array
    {
        $list = [];

        foreach ($this->variants as $variant) {
            $list[] = [
                'id' => $variant->id ?? '',
                'price' => $variant->price ?? 0,
                'sales_price' => $variant->sales_price ?? 0,
                'stock_quantity' => $variant->stock->quantity ?? 0,
                'oh_hold' => $variant->stock->on_hold ?? 0,
                'available_quantity' => ($variant->stock->quantity ?? 0) - ($variant->stock->on_hold ?? 0),
                'is_default' => $this->is_default ?? '',
                'variant_options' => $this->formatVariantOptions($variant->variantOptions),
            ];
        }

        return $list;
    }

    private function formatVariantOptions($variantOptions): array
    {
        $list = [];

        foreach ($variantOptions as $value) {
            $list[] = [
                'attribute_name' => $value->attribute->name ?? '',
                'value' => $value->value,
            ];
        }

        return $list;
    }
}
