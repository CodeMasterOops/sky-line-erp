<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'title' => $this->title ?? '',
            'slug' => $this->slug ?? '',
            'sort_order' => $this->sort_order ?? '',
            'featured_image' => $this->featured_image ?? '',
            'featured_image_url' => $this->featured_image_url ?? '',
            'description' => $this->description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'status' => $this->status ?? true,
        ];
    }
}
