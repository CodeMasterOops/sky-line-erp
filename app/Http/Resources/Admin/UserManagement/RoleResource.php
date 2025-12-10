<?php

namespace App\Http\Resources\Admin\UserManagement;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'permissions' => $this->when($request->routeIs('api.admin.role.show'), fn () => $this->permissions),
        ];
    }
}
