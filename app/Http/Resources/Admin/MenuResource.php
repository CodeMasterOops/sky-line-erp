<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'menu_type' => $this->menu_type ?? '',
            'title' => $this->title ?? '',
            'url' => $this->url ?? '',
            'parent_id' => $this->parent_id ?? '',
            'parent' => self::make($this->whenLoaded('parent')),
            'image' => $this->image ?? '',
            'image_url' => $this->image_url ?? '',
            'sort_order' => $this->sort_order ?? '',
            'status' => $this->status ?? false,
            'children' => self::collection($this->whenLoaded('children')),
        ];
    }
}
