<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'address' => $this->address ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'pan' => $this->pan ?? '',
            'is_head_office' => $this->is_head_office ?? false,
            'is_active' => $this->is_active ?? false,
        ];
    }
}
