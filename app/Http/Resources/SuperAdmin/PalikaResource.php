<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PalikaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'district_id' => $this->district_id,
            'province_id' => $this->when(
                $this->relationLoaded('district') && $this->district,
                fn () => (int) $this->district->province_id
            ),
            'district_name' => $this->whenLoaded('district', fn () => $this->district->name, ''),
            'province_name' => $this->when(
                $this->relationLoaded('district') && $this->district?->relationLoaded('province'),
                fn () => $this->district->province->name
            ),
            'name' => $this->name,
            'sort_order' => $this->sort_order,
        ];
    }
}
