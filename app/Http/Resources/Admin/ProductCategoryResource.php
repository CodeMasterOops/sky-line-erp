<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'parent_id' => $this->parent_id ?? '',
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
            'sort_order' => $this->sort_order ?? '',
            'thumbnail_image' => $this->thumbnail_image ?? '',
            'thumbnail_image_url' => $this->thumbnail_image_url ?? '',
            'banner_image' => $this->banner_image ?? '',
            'banner_image_url' => $this->banner_image_url ?? '',
            'description' => $this->description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'is_featured' => $this->is_featured ?? true,
            'status' => $this->status ?? true,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
