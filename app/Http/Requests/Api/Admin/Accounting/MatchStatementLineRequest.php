<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Models\BankStatementLine;
use App\Http\Validation\BranchScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class MatchStatementLineRequest extends FormRequest
{
    /**
     * The route-bound statement line must belong to the current tenant. Authorizing
     * here returns 403 before validation runs, preserving the cross-tenant boundary.
     */
    public function authorize(): bool
    {
        $line = $this->route('bankStatementLine');

        if (! $line instanceof BankStatementLine) {
            return false;
        }

        $bankAccount = $line->bankAccount()->withoutGlobalScopes()->first();

        return $bankAccount !== null
            && $bankAccount->company_id === auth('admin')->user()->company_id;
    }

    public function rules(): array
    {
        return [
            'journal_item_id' => [
                'required',
                'integer',
                BranchScopedExists::child('journal_items', 'journal_id', 'journals'),
            ],
        ];
    }
}
