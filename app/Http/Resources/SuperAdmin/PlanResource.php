<?php

namespace App\Http\Resources\SuperAdmin;

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
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_recommended' => $this->is_recommended,
            'sort_order' => $this->sort_order,
            'subscriptions_count' => $this->whenCounted('subscriptions'),
            'active_subscriptions_count' => $this->when(
                isset($this->active_subscriptions_count),
                $this->active_subscriptions_count
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
