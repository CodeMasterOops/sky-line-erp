<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use App\Enums\NotificationTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'type' => $this->type,
            'notification_type' => NotificationTypeEnum::tryFrom($this->type)?->label(),
            'time' => $this->created_at->diffForHumans(),
            'data' => $this->data ?? [],
            'read_at' => $this->read_at?->diffForHumans(),
        ];
    }
}
