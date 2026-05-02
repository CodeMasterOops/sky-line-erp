<?php

namespace App\Http\Resources\Admin\HR;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name ?? '',
            'type' => $this->type ?? '',
            'type_label' => $this->type?->label() ?? '',
            'calculation_type' => $this->calculation_type ?? '',
            'is_taxable' => $this->is_taxable,
            'is_active' => $this->is_active,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
        ];
    }
}
