<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description ?? '',
            'price_monthly' => $this->price_monthly,
            'price_yearly' => $this->price_yearly,
            'features' => $this->features ?? [],
            'is_recommended' => $this->is_recommended,
        ];
    }
}
