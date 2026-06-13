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
            'payment_terms' => $this->payment_terms?->value ?? 'net30',
            'payment_terms_label' => $this->payment_terms?->label() ?? 'Net 30 Days',
            'credit_days' => $this->credit_days ?? 30,
            'custom_days' => $this->custom_days ?? null,
            'is_active' => $this->is_active ?? '',
            'discount_type' => $this->discount?->type ?? '',
            'discount_value' => $this->discount?->value ?? '',
        ];
    }
}
