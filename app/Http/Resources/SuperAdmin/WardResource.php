<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'palika_id' => $this->palika_id,
            'district_id' => $this->when(
                $this->relationLoaded('palika') && $this->palika,
                fn () => (int) $this->palika->district_id
            ),
            'province_id' => $this->when(
                $this->relationLoaded('palika') && $this->palika?->relationLoaded('district') && $this->palika->district,
                fn () => (int) $this->palika->district->province_id
            ),
            'palika_name' => $this->whenLoaded('palika', fn () => $this->palika->name, ''),
            'district_name' => $this->when(
                $this->relationLoaded('palika') && $this->palika?->relationLoaded('district'),
                fn () => $this->palika->district->name
            ),
            'province_name' => $this->when(
                $this->relationLoaded('palika')
                && $this->palika?->relationLoaded('district')
                && $this->palika->district?->relationLoaded('province'),
                fn () => $this->palika->district->province->name
            ),
            'name' => $this->name,
            'postal_code' => $this->postal_code,
            'sort_order' => $this->sort_order,
        ];
    }
}
