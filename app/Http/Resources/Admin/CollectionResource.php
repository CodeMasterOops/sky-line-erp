<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'sort_order' => $this->sort_order ?? 0,
            'description' => $this->description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'status' => $this->status ?? true,
            'products' => $this->whenLoaded('products', function () {
                return $this->productList();
            }),
        ];
    }

    private function productList(): array
    {
        $list = [];

        foreach ($this->products as $product) {
            $list[] = [
                'id' => $product->id,
                'name' => $product->name,
            ];
        }

        return $list;
    }
}
