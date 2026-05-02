<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'parent_id' => ['nullable', TRule::exists('account_groups', 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($baseRules, [
                'code' => ['required', 'string', 'max:255', TRule::unique('account_groups')->withoutTrashed()],
            ]),
            'PUT' => array_merge($baseRules, [
                'code' => ['required', 'string', 'max:255', TRule::unique('account_groups')->withoutTrashed()->ignore($this->account_group)],
            ]),
        };
    }
}
