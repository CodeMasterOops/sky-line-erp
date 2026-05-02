<?php

namespace App\Http\Resources\Admin\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'leave_type_id' => $this->leave_type_id,
            'from_date' => $this->from_date?->format('Y-m-d'),
            'to_date' => $this->to_date?->format('Y-m-d'),
            'days' => $this->days,
            'reason' => $this->reason ?? '',
            'status' => $this->status ?? '',
            'status_label' => $this->status?->label() ?? '',
            'rejection_reason' => $this->rejection_reason ?? '',
            'approved_at' => $this->approved_at?->format('Y-m-d H:i'),
            'employee' => $this->whenLoaded('employee', fn () => EmployeeResource::make($this->employee)),
            'leave_type' => $this->whenLoaded('leaveType', fn () => LeaveTypeResource::make($this->leaveType)),
        ];
    }
}
