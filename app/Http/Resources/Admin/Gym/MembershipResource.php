<?php

namespace App\Http\Resources\Admin\Gym;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MembershipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'membership_no' => $this->membership_no,
            'member_id' => $this->member_id,
            'member_name' => $this->whenLoaded('member', fn () => $this->member?->party?->name),
            'member_code' => $this->whenLoaded('member', fn () => $this->member?->member_code),
            'membership_plan_id' => $this->membership_plan_id,
            'plan_name' => $this->whenLoaded('membershipPlan', fn () => $this->membershipPlan?->name),
            'duration_label' => $this->whenLoaded('membershipPlan', fn () => $this->membershipPlan?->duration_label),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days_remaining' => $this->daysRemaining(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'price' => $this->price,
            'discount_amount' => $this->discount_amount,
            'joining_fee' => $this->joining_fee,
            'payable_amount' => $this->payable_amount,
            'invoice_id' => $this->invoice_id,
            'invoice_no' => $this->whenLoaded('invoice', fn () => $this->invoice?->invoice_no),
            'renewed_from_id' => $this->renewed_from_id,
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancel_reason' => $this->cancel_reason,
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
