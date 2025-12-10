<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnnouncementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'title' => $this->title ?? '',
            'message' => $this->message ?? '',
            'link' => $this->link ?? '',
            'sort_order' => $this->sort_order ?? 0,
            'is_active' => $this->is_active ?? true,
        ];
    }
}
