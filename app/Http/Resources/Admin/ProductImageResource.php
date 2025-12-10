<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductImageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'title' => $this->title ?? '',
            'image' => $this->image ?? '',
            'image_url' => $this->image_url ?? '',
            'image_alt' => $this->image_alt ?? '',
            'sort_order' => $this->sort_order ?? '',
        ];
    }
}
