<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'parent_id' => $this->parent_id ?? '',
            'name' => $this->name ?? '',
            'slug' => $this->slug ?? '',
        ];
    }
}
