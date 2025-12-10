<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'thumbnail_image' => $this->thumbnail_image ?? '',
            'thumbnail_image_url' => $this->thumbnail_image_url ?? '',
            'description' => $this->description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'is_featured' => $this->is_featured ?? false,
            'status' => $this->status ?? true,
        ];
    }
}
