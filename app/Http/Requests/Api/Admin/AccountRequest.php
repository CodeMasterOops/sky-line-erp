<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $baseRules = [
            'account_group_id' => ['nullable', TRule::exists('account_groups', 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($baseRules, [
                'code' => ['required', 'string', 'max:255', TRule::unique('accounts')->withoutTrashed()],
            ]),
            'PUT' => array_merge($baseRules, [
                'code' => ['required', 'string', 'max:255', TRule::unique('accounts')->withoutTrashed()->ignore($this->account)],
            ]),
        };
    }
}
