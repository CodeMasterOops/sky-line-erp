<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\LeaveStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'leave_type_id' => ['required', 'exists:leave_types,id'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'gte:from_date'],
            'days' => ['required', 'numeric', 'min:0.5'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
