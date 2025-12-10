<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'type' => $this->type->label() ?? '',
            'quantity' => $this->quantity ?? 0,
            'remarks' => $this->remarks ?? '',
            'created_at' => $this->created_at->format('Y-m-d, g:i A'),
        ];
    }
}
