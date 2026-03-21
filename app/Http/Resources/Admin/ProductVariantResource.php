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
            'sales_price' => $this->sales_price ?? 0,
            'purchase_price' => $this->purchase_price ?? 0,
            'is_default' => $this->is_default ?? false,
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
