<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Enums\LeadStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class LeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(LeadStatusEnum::class)],
            'follow_up_note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
