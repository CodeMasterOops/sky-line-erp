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
            'vendor_id' => $this->vendor_id ?? '',
            'vendor_name' => $this->whenLoaded('vendor', function () {
                return $this->vendor->vendor_name ?? '';
            }),
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'thumbnail_image' => $this->thumbnail_image ?? '',
            'thumbnail_image_url' => $this->thumbnail_image_url ?? '',
            'brand_id' => $this->brand_id ?? '',
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'has_variants' => $this->has_variants ?? false,
            'stock_quantity' => $this->stocks_sum_quantity ?? 0,
            'specification' => $this->specification ?? '',
            'ingredients' => $this->ingredients ?? '',
            'description' => $this->description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'is_active' => $this->is_active ?? true,
            'categories' => $this->whenLoaded('categories', function () {
                return $this->formatCategories();
            }),
            'defaultVariant' => ProductVariantResource::make($this->defaultVariant),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'attributes' => $this->whenLoaded('attributeValues', function () {
                return $this->attributes ?? [];
            }),
            'attribute_value_ids' => $this->whenLoaded('attributeValues', function () {
                return $this->attributeValues->pluck('id')->toArray();
            }),
        ];
    }

    private function formatCategories(): array
    {
        $list = [];

        foreach ($this->categories as $category) {
            $list[] = [
                'id' => $category->id,
                'name' => $category->name,
            ];
        }

        return $list;
    }
}
