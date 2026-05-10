<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChequeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id ?? '',
            'company_id' => $this->company_id ?? '',
            'fiscal_year_id' => $this->fiscal_year_id ?? '',
            'party_id' => $this->party_id ?? '',
            'party' => $this->whenLoaded('party', function () {
                return [
                    'id' => $this->party?->id ?? '',
                    'name' => $this->party?->name ?? '',
                ];
            }),
            'bank_account_id' => $this->bank_account_id ?? '',
            'bank_account' => $this->whenLoaded('bankAccount', function () {
                return [
                    'id' => $this->bankAccount?->id ?? '',
                    'bank_name' => $this->bankAccount?->bank_name ?? '',
                    'account_number' => $this->bankAccount?->account_number ?? '',
                ];
            }),
            'cheque_no' => $this->cheque_no ?? '',
            'bank_name' => $this->bank_name ?? '',
            'bank_branch' => $this->bank_branch ?? '',
            'cheque_date' => $this->cheque_date?->format('Y-m-d') ?? '',
            'deposit_date' => $this->deposit_date?->format('Y-m-d') ?? '',
            'cleared_date' => $this->cleared_date?->format('Y-m-d') ?? '',
            'amount' => $this->amount ?? 0,
            'type' => $this->type ?? '',
            'status' => $this->status ?? '',
            'reference_type' => $this->reference_type ?? '',
            'reference_id' => $this->reference_id ?? '',
            'gl_journal_id' => $this->gl_journal_id ?? '',
            'create_user_id' => $this->create_user_id ?? '',
            'create_user' => $this->whenLoaded('createUser', function () {
                return [
                    'id' => $this->createUser?->id ?? '',
                    'name' => $this->createUser?->name ?? '',
                ];
            }),
            'remarks' => $this->remarks ?? '',
            'created_at' => $this->created_at?->format('Y-m-d') ?? '',
            'updated_at' => $this->updated_at?->format('Y-m-d') ?? '',
        ];
    }
}
