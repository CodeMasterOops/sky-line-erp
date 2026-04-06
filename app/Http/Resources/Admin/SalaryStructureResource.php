<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'effective_from' => $this->effective_from?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'employee' => $this->whenLoaded('employee', fn () => EmployeeResource::make($this->employee)),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'salary_component_id' => $item->salary_component_id,
                'amount' => $item->amount,
                'percentage' => $item->percentage,
                'component' => $item->relationLoaded('salaryComponent') ? SalaryComponentResource::make($item->salaryComponent) : null,
            ])),
        ];
    }
}
