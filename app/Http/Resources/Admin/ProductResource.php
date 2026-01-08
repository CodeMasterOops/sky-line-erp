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
            'sku' => $this->sku ?? '',
            'image' => $this->image ?? '',
            'unit_id' => $this->unit_id ?? '',
            'brand_id' => $this->brand_id ?? '',
            'sales_price' => $this->sales_price ?? 0,
            'purchase_price' => $this->purchase_price ?? 0,
            'reorder_quantity' => $this->reorder_quantity ?? 0,
            'description' => $this->description ?? '',
        ];
    }
}
