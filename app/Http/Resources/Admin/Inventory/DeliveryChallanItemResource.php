<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeliveryChallanItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'delivery_challan_id' => $this->delivery_challan_id ?? '',
            'product_variant_id' => $this->product_variant_id ?? '',
            'unit_id' => $this->unit_id ?? '',
            'quantity' => (float) ($this->quantity ?? 0),
            'rate' => (float) ($this->rate ?? 0),
            'remarks' => $this->remarks ?? '',
            'product_variant' => $this->whenLoaded('productVariant', fn () => ProductVariantResource::make($this->productVariant)),
            'unit' => $this->whenLoaded('unit', fn () => UnitResource::make($this->unit)),
        ];
    }
}
