<?php

namespace App\Http\Resources\Admin\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkScheduleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'start_time' => $this->start_time ? substr($this->start_time, 0, 5) : null,
            'end_time' => $this->end_time ? substr($this->end_time, 0, 5) : null,
            'grace_minutes' => $this->grace_minutes,
            'standard_hours_per_day' => $this->standard_hours_per_day,
            'overtime_multiplier' => $this->overtime_multiplier,
            'overtime_enabled' => $this->overtime_enabled,
            'weekly_off_days' => $this->weeklyOffDays(),
            'is_default' => $this->is_default,
        ];
    }
}
