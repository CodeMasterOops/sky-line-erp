<?php

namespace App\Http\Resources\Admin\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description ?? '',
            'duration_unit' => $this->duration_unit?->value,
            'duration_value' => $this->duration_value,
            'preset' => $this->preset?->value,
            'preset_label' => $this->preset?->label(),
            'duration_label' => $this->duration_label,
            'price' => $this->price,
            'joining_fee' => $this->joining_fee,
            'grace_days' => $this->grace_days,
            'max_freeze_days' => $this->max_freeze_days,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name),
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
