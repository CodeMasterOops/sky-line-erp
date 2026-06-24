<?php

namespace App\Http\Requests\Api\Admin\HR;

use Illuminate\Foundation\Http\FormRequest;

class WorkScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'standard_hours_per_day' => ['required', 'numeric', 'min:1', 'max:24'],
            'overtime_multiplier' => ['required', 'numeric', 'min:1', 'max:5'],
            'overtime_enabled' => ['nullable', 'boolean'],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'min:0', 'max:6'],
        ];
    }
}
