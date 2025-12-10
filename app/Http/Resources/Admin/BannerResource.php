<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'title' => $this->title ?? '',
            'image' => $this->image ?? '',
            'image_url' => $this->image_url ?? '',
            'link' => $this->link ?? '',
            'description' => $this->description ?? '',
            'sort_order' => $this->sort_order ?? 0,
            'status' => $this->status ?? true,
        ];
    }
}
