<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'type' => $this->type ?? '',
            'type_label' => $this->type?->label() ?? '',
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'phone' => $this->phone ?? '',
            'email' => $this->email ?? '',
            'pan' => $this->pan ?? '',
            'address' => $this->address ?? '',
            'credit_limit' => $this->credit_limit ?? '',
            'is_active' => $this->is_active ?? '',
        ];
    }
}
