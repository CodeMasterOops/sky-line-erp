<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'product_category_id' => $this->product_category_id ?? '',
            'product_type' => $this->product_type ?? '',
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'image' => $this->image ?? '',
            'unit_id' => $this->unit_id ?? '',
            'brand_id' => $this->brand_id ?? '',
            'has_variants' => (bool) ($this->has_variants ?? false),
            'reorder_quantity' => $this->reorder_quantity ?? 0,
            'description' => $this->description ?? '',
            'category' => $this->productCategory ? $this->productCategory->name : '',
            'brand' => $this->brand ? $this->brand->name : '',
            'unit' => $this->unit ? $this->unit->name : '',
            'defaultVariant' => ProductVariantResource::make($this->defaultVariant),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
