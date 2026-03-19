<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'account_group_id' => $this->account_group_id ?? '',
            'account_group' => AccountGroupResource::make($this->whenLoaded('accountGroup')),
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'category' => $this->category ?? '',
            'description' => $this->description ?? '',
            'is_active' => $this->is_active ?? false,
        ];
    }
}
