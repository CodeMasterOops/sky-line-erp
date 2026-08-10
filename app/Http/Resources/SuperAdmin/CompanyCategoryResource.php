<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'icon' => $this->icon ?? '',
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'sort_order' => $this->sort_order,
            'modules' => $this->whenLoaded('modules', fn (): array => $this->defaultModuleKeys()),
            'companies_count' => $this->whenCounted('companies'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
