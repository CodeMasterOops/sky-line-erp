<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use Illuminate\Validation\Rule;
use App\Models\BankStatementLine;
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
        $companyId = auth('admin')->user()->company_id;

        return [
            'journal_item_id' => [
                'required',
                'integer',
                Rule::exists('journal_items', 'id')->where(function ($query) use ($companyId) {
                    $query->whereIn('journal_id', function ($sub) use ($companyId) {
                        $sub->select('id')->from('journals')
                            ->where('company_id', $companyId)
                            ->whereNull('deleted_at');
                    });
                }),
            ],
        ];
    }
}
