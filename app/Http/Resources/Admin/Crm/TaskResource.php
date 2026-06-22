<?php

namespace App\Http\Resources\Admin\Crm;

use App\Models\Party;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'priority_label' => $this->priority?->label(),
            'status' => $this->status?->value,
            'status_label' => $this->status?->label(),
            'assigned_to_user_id' => $this->assigned_to_user_id,
            'assigned_to_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'created_by_user_id' => $this->created_by_user_id,
            'party_id' => $this->taskable_type === Party::class ? $this->taskable_id : null,
            'party_name' => $this->whenLoaded('taskable', fn () => $this->taskable?->name),
            'due_date' => $this->due_date?->toDateString(),
            'reminder_at' => $this->reminder_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
