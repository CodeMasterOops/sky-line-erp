<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'voucher_no' => $this->voucher_no ?? '',
            'reference_no' => $this->reference_no ?? '',
            'date' => $this->date?->toDateString() ?? '',
            'remarks' => $this->remarks ?? '',
            'type' => $this->type?->value ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'approve_user_id' => $this->approve_user_id ?? '',
            'approved_at' => $this->approved_at ?? null,
            'status' => $this->status?->value ?? '',
            'items' => JournalItemResource::collection($this->whenLoaded('journalItems')),
        ];
    }
}
