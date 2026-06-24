<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SecurityActivity
 */
class SecurityActivityResource extends JsonResource
{
    private const LABELS = [
        'login' => 'Signed in',
        'logout' => 'Signed out',
        'password_changed' => 'Password changed',
        'deactivated' => 'Account deactivated',
        'account_deleted' => 'Account deleted',
        'device_revoked' => 'Device signed out',
    ];

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event,
            'label' => self::LABELS[$this->event] ?? ucfirst(str_replace('_', ' ', (string) $this->event)),
            'description' => $this->description,
            'ip_address' => $this->ip_address,
            'device' => DeviceResource::userAgentLabel($this->user_agent),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
