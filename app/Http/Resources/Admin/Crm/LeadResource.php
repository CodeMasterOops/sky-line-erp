<?php

namespace App\Http\Resources\Admin\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profile = $this->leadProfile;

        return [
            'id' => $this->id,
            'type' => $this->type?->value,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'email' => $this->email,
            'pan' => $this->pan,
            'address' => $this->address,
            'is_active' => $this->is_active,
            'status' => $profile?->status?->value,
            'status_label' => $profile?->status?->label(),
            'source' => $profile?->source,
            'assigned_to_user_id' => $profile?->assigned_to_user_id,
            'assigned_to_name' => $this->whenLoaded('leadProfile', fn () => $profile?->assignedUser?->name),
            'expected_value' => $profile?->expected_value,
            'expected_close_date' => $profile?->expected_close_date?->toDateString(),
            'next_follow_up_at' => $profile?->next_follow_up_at?->toIso8601String(),
            'qualified_at' => $profile?->qualified_at?->toIso8601String(),
            'converted_at' => $profile?->converted_at?->toIso8601String(),
            'lost_reason' => $profile?->lost_reason,
            'contact_persons' => ContactPersonResource::collection($this->whenLoaded('contactPersons')),
        ];
    }
}
