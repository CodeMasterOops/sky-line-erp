<?php

namespace App\Http\Resources\Admin\Inventory;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'parent_id' => $this->parent_id,
            'name' => $this->name ?? '',
            'description' => $this->description ?? '',
            'full_path' => $this->fullPath(),
            'is_leaf' => $this->isLeaf(),
            'parent' => $this->when(
                $this->relationLoaded('parent'),
                fn () => $this->parent
                    ? [
                        'id' => $this->parent->id,
                        'name' => $this->parent->name,
                    ]
                    : null,
            ),
        ];
    }
}
