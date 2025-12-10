<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'title' => $this->title ?? '',
            'slug' => $this->slug ?? '',
            'products_count' => $this->whenCounted('products'),
            'description' => $this->description ?? '',
            'status' => $this->status ?? true,
        ];
    }
}
