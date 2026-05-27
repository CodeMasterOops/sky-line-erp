<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'plan_id' => $this->plan_id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'billing_cycle' => $this->billing_cycle->value,
            'billing_cycle_label' => $this->billing_cycle->label(),
            'price' => $this->price,
            'monthly_recurring_revenue' => $this->monthlyRecurringRevenue(),
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'notes' => $this->notes ?? '',
            'company' => CompanyResource::make($this->whenLoaded('company')),
            'plan' => PlanResource::make($this->whenLoaded('plan')),
            'assigned_by' => $this->whenLoaded('assignedBy', fn () => [
                'id' => $this->assignedBy->id,
                'name' => $this->assignedBy->name,
                'email' => $this->assignedBy->email,
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
