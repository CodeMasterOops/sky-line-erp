<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'status' => $this->status ?? true,
        ];
    }
}
