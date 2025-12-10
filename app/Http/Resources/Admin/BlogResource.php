<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BlogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'blog_category_id' => $this->blog_category_id ?? '',
            'category_name' => $this->whenLoaded('blogCategory', function () {
                return $this->blogCaegory->name ?? '';
            }),
            'title' => $this->title ?? '',
            'slug' => $this->slug ?? '',
            'sub_title' => $this->sub_title ?? '',
            'publish_date' => $this->publish_date ?? '',
            'featured_image' => $this->featured_image ?? '',
            'featured_image_url' => $this->featured_image_url ?? '',
            'authors' => AuthorResource::collection($this->whenLoaded('authors')),
            'description' => $this->description ?? '',
            'short_description' => $this->short_description ?? '',
            'meta_title' => $this->meta_title ?? '',
            'meta_keywords' => $this->meta_keywords ?? '',
            'meta_description' => $this->meta_description ?? '',
            'status' => $this->status ?? true,
        ];
    }
}
