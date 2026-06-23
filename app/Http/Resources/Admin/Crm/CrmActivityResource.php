<?php

namespace App\Http\Resources\Admin\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CrmActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'properties' => $this->properties,
            'causer_id' => $this->causer_id,
            'causer_name' => $this->whenLoaded('causer', fn () => $this->causer?->name),
            'occurred_at' => $this->occurred_at?->toIso8601String(),
        ];
    }
}
