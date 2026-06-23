<?php

namespace App\Http\Requests\Api\Admin\Crm;

use Illuminate\Validation\Rule;
use App\Enums\CrmLeadStatusEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CrmLeadStatusEnum::class)],
            'lost_reason' => ['nullable', 'required_if:status,'.CrmLeadStatusEnum::Lost->value, 'string', 'max:255'],
        ];
    }
}
