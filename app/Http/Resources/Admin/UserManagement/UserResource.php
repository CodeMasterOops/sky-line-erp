<?php

namespace App\Http\Resources\Admin\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'status' => $this->status ?? true,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}
