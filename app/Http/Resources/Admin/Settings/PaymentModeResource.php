<?php

namespace App\Http\Resources\Admin\Settings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentModeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'name' => $this->name ?? '',
            'is_active' => (bool) ($this->is_active ?? false),
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => [
                'id' => $this->bankAccount->id,
                'bank_name' => $this->bankAccount->bank_name,
                'account_number' => $this->bankAccount->account_number,
                'account_id' => $this->bankAccount->account_id,
            ]),
        ];
    }
}
