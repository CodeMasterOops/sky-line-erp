<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'days_allowed' => $this->days_allowed,
            'is_paid' => $this->is_paid,
            'is_active' => $this->is_active,
        ];
    }
}
