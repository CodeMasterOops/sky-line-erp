<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeadResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_name' => $this->company_name,
            'pan' => $this->pan ?? '',
            'registration_number' => $this->registration_number ?? '',
            'business_type' => $this->business_type?->value,
            'business_type_label' => $this->business_type?->label(),
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'plan_interest' => $this->plan_interest ?? '',
            'branch_count' => $this->branch_count,
            'note' => $this->note ?? '',
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'follow_up_note' => $this->follow_up_note ?? '',
            'followed_up_at' => $this->followed_up_at?->toDateTimeString(),
            'ip_address' => $this->ip_address ?? '',
            'source' => $this->source ?? '',
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
