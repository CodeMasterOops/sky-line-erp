<?php

namespace App\Http\Resources\Admin\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'date' => $this->date?->format('Y-m-d'),
            'check_in' => $this->check_in ?? '',
            'check_out' => $this->check_out ?? '',
            'status' => $this->status ?? '',
            'status_label' => $this->status?->label() ?? '',
            'note' => $this->note ?? '',
            'employee' => $this->whenLoaded('employee', fn () => EmployeeResource::make($this->employee)),
        ];
    }
}
