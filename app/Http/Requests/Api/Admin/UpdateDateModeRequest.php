<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\DateModeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDateModeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date_mode' => ['required', 'string', Rule::enum(DateModeEnum::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_mode.required' => 'A date mode is required.',
            'date_mode.Illuminate\Validation\Rules\Enum' => 'The selected date mode is invalid.',
        ];
    }
}
