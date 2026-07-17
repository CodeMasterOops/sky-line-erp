<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $groupType = $this->whenLoaded('accountGroup', fn () => $this->accountGroup?->resolvedAccountType());

        return [
            'id' => $this->id ?? '',
            'account_group_id' => $this->account_group_id ?? '',
            'account_group' => AccountGroupResource::make($this->whenLoaded('accountGroup')),
            'name' => $this->name ?? '',
            'code' => $this->code ?? '',
            'category' => $this->category ?? '',
            'cash_bank_type' => $this->resolveCashBankType(),
            'description' => $this->description ?? '',
            'is_active' => $this->is_active ?? false,
            'normal_balance' => $groupType instanceof \App\Enums\AccountGroupTypeEnum ? $groupType->normalBalance() : null,
        ];
    }

    /**
     * A stable cash / bank classification derived from the free-text category,
     * normalised for case and whitespace so downstream ledger filters never
     * depend on the exact stored string (e.g. 'Cash' vs ' cash ').
     */
    private function resolveCashBankType(): ?string
    {
        return match (strtolower(trim((string) $this->category))) {
            'cash' => 'cash',
            'bank' => 'bank',
            default => null,
        };
    }
}
