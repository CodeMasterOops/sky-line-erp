<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'vendor_id' => $this->vendor_id ?? '',
            'vendor_name' => $this->whenLoaded('vendor', function () {
                return $this->vendor->vendor_name ?? '';
            }),
            'product' => $this->whenLoaded('productVariant', function () {
                return $this->formatVariant();
            }),
            'unit_price' => $this->unit_price ?? '',
            'quantity' => $this->quantity ?? '',
            'total' => $this->total ?? '',
        ];
    }

    private function formatVariant()
    {
        return [
            'name' => $this->productVariant->product->name ?? '',
            'image' => $this->productVariant->product->thumbnail_image_url ?? '',
        ];
    }
}
