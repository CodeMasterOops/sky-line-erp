<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\EmploymentTypeEnum;
use App\Enums\EmployeeStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class EmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'department_id' => ['nullable', 'exists:departments,id'],
            'designation_id' => ['nullable', 'exists:designations,id'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],
            'dob' => ['nullable', 'date'],
            'join_date' => ['required', 'date'],
            'employment_type' => ['nullable', Rule::enum(EmploymentTypeEnum::class)],
            'status' => ['nullable', Rule::enum(EmployeeStatusEnum::class)],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'bank_account_no' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'employee_code' => ['required', 'string', 'max:50', TRule::unique('employees')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'employee_code' => ['required', 'string', 'max:50', TRule::unique('employees')->withoutTrashed()->ignore($this->employee)],
            ]),
            default => $validations,
        };
    }
}
