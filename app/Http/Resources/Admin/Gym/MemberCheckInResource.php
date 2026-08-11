<?php

namespace App\Http\Resources\Admin\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberCheckInResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_id' => $this->member_id,
            'member_name' => $this->whenLoaded('member', fn () => $this->member?->party?->name),
            'member_code' => $this->whenLoaded('member', fn () => $this->member?->member_code),
            'membership_id' => $this->membership_id,
            'checked_in_at' => $this->checked_in_at?->toIso8601String(),
            'checked_out_at' => $this->checked_out_at?->toIso8601String(),
            'duration_minutes' => $this->checked_out_at
                ? (int) $this->checked_in_at->diffInMinutes($this->checked_out_at)
                : null,
            'method' => $this->method?->value,
            'method_label' => $this->method?->label(),
            'notes' => $this->notes,
            // A visit with no term behind it is the front desk's cue to ask
            // about a renewal.
            'without_membership' => $this->membership_id === null,
        ];
    }
}
