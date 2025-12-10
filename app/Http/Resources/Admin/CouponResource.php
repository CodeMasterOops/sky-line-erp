<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'vendor_id' => $this->vendor_id ?? '',
            'title' => $this->title ?? '',
            'code' => $this->code ?? '',
            'start_date' => $this->start_date?->format('Y-m-d H:i') ?? '',
            'end_date' => $this->end_date?->format('Y-m-d H:i') ?? '',
            'discount_type' => $this->discount_type ?? '',
            'discount' => $this->discount ?? '',
            'max_discount_amount' => $this->max_discount_amount ?? '',
            'usage_limit' => $this->usage_limit ?? '',
            'same_user_limit' => $this->same_user_limit ?? '',
            'used' => $this->used ?? '',
            'is_active' => $this->is_active ?? true,
            'products' => $this->whenLoaded('products', function () {
                return $this->products();
            }),
        ];
    }

    private function products(): array
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
