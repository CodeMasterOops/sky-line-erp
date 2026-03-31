<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'journal_id' => $this->journal_id ?? '',
            'account_id' => $this->account_id ?? '',
            'account' => $this->account ? $this->account->name : '',
            'dr_amount' => $this->dr_amount ?? 0,
            'cr_amount' => $this->cr_amount ?? 0,
            'remarks' => $this->remarks ?? '',
        ];
    }
}
