<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->id,
            'name' => $this->user->name,
            'email' => $this->user->email,
            'is_active' => $this->is_active,
            'branch_role' => $this->role?->only(['id', 'name']),
            'assigned_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
