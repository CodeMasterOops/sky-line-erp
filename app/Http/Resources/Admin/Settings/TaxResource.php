<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'rate' => $this->rate ?? 0,
            'type' => $this->type?->value ?? null,
            'type_label' => $this->type?->label() ?? null,
            'tds_category' => $this->tds_category?->value ?? null,
            'tds_category_label' => $this->tds_category?->label() ?? null,
            'is_system' => $this->is_system ?? false,
        ];
    }
}
