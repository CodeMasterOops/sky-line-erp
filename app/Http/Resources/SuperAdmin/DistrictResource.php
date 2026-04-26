<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DistrictResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'province_id' => $this->province_id,
            'province_name' => $this->whenLoaded('province', fn () => $this->province->name, ''),
            'name' => $this->name,
            'sort_order' => $this->sort_order,
        ];
    }
}
