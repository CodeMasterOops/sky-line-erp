<?php

namespace App\Http\Resources\SuperAdmin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'email' => $this->email ?? '',
            'phone' => $this->phone ?? '',
            'profile_photo_url' => $this->profile_photo_url ?? '',
        ];
    }
}
