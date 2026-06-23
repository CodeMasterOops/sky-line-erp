<?php

namespace App\Http\Requests\Api\Admin\Crm;

use App\Tenancy\TRule;
use App\Rules\NepaliPan;
use Illuminate\Validation\Rule;
use App\Enums\CrmLeadStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isUpdate = $this->method() === 'PUT' || $this->method() === 'PATCH';
        $ignore = $isUpdate ? $this->party : null;

        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'code' => [
                $isUpdate ? 'required' : 'nullable',
                'string',
                'max:255',
                TRule::unique('parties')->withoutTrashed()->ignore($ignore),
            ],
            'phone' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()->ignore($ignore)],
            'email' => ['nullable', 'email', TRule::unique('parties')->withoutTrashed()->ignore($ignore)],
            'pan' => ['nullable', 'string', new NepaliPan, TRule::unique('parties')->withoutTrashed()->ignore($ignore)],

            'status' => ['nullable', Rule::enum(CrmLeadStatusEnum::class)],
            'source' => ['nullable', 'string', 'max:255'],
            'assigned_to_user_id' => ['nullable', 'integer', TRule::exists('users', 'id')],
            'expected_value' => ['nullable', 'numeric', 'min:0'],
            'expected_close_date' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}
