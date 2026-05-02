<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountGroupResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'parent_id' => $this->parent_id ?? '',
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'description' => $this->description ?? '',
            'is_active' => $this->is_active ?? false,
        ];
    }
}
