<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class MatchStatementLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = auth('admin')->user()->company_id;

        return [
            'journal_item_id' => [
                'required',
                'integer',
                Rule::exists('journal_items', 'id')->where(function ($query) use ($companyId) {
                    $query->join('journals', 'journals.id', '=', 'journal_items.journal_id')
                        ->where('journals.company_id', $companyId)
                        ->whereNull('journals.deleted_at');
                }),
            ],
        ];
    }
}
