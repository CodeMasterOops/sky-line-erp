<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountGroupTreeResource extends JsonResource
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
            'accounts' => AccountResource::collection($this->whenLoaded('accounts')),
            'children' => AccountGroupTreeResource::collection($this->whenLoaded('childrenRecursive')),
        ];
    }
}
