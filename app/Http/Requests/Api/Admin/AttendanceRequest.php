<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\AttendanceStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::enum(AttendanceStatusEnum::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
