<?php

namespace App\Http\Resources\Admin\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A member as one flat entity — the party half and the gym half merged, so the
 * client never has to know they are two rows.
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_code' => $this->member_code,
            'party_id' => $this->party_id,

            'name' => $this->party?->name ?? '',
            'phone' => $this->party?->phone ?? '',
            'email' => $this->party?->email ?? '',
            'address' => $this->party?->address ?? '',

            'photo_url' => $this->photo_url,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'gender' => $this->gender?->value,
            'gender_label' => $this->gender?->label(),
            'blood_group' => $this->blood_group?->value,
            'blood_group_label' => $this->blood_group?->label(),
            'occupation' => $this->occupation,
            'emergency_contact_name' => $this->emergency_contact_name,
            'emergency_contact_phone' => $this->emergency_contact_phone,
            'height_cm' => $this->height_cm,
            'weight_kg' => $this->weight_kg,
            'medical_notes' => $this->medical_notes,
            'joined_on' => $this->joined_on?->toDateString(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'source' => $this->source,
            'referred_by_member_id' => $this->referred_by_member_id,
            'referred_by_name' => $this->whenLoaded('referredBy', fn () => $this->referredBy?->party?->name),
            'assigned_trainer_id' => $this->assigned_trainer_id,
            'trainer_name' => $this->whenLoaded('trainer', fn () => $this->trainer?->name),
            'branch_id' => $this->branch_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
