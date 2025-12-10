<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductVariantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'sku' => $this->sku ?? '',
            'price' => $this->price ?? 0,
            'sales_price' => $this->sales_price ?? 0,
            'weight' => $this->weight ?? 0,
            'length' => $this->length ?? 0,
            'width' => $this->width ?? 0,
            'height' => $this->height ?? 0,
            'image' => $this->image ?? '',
            'image_url' => $this->image_url ?? '',
            'image_alt' => $this->image_alt ?? '',
            'is_default' => $this->is_default ?? '',
            'variant_options' => $this->whenLoaded('variantOptions', function () {
                return $this->formatvariantOptions();
            }),
        ];
    }

    private function formatvariantOptions()
    {
        $list = [];

        foreach ($this->variantOptions as $value) {
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
