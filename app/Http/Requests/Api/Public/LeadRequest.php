<?php

namespace App\Http\Requests\Api\Public;

use App\Enums\BusinessTypeEnum;
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
            // Honeypot — bots fill this; humans leave it blank; allow it through so the controller can silently swallow it
            'website' => ['nullable', 'string'],

            'company_name' => ['required', 'string', 'max:255'],
            'pan' => ['nullable', 'string', 'max:50'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'business_type' => ['required', Rule::enum(BusinessTypeEnum::class)],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc,dns', 'max:255', Rule::unique('leads', 'email')],
            'phone' => ['required', 'string', 'regex:/^[0-9+\-\s]{7,20}$/', Rule::unique('leads', 'phone')],
            'plan_interest' => ['nullable', 'string', 'max:100'],
            'branch_count' => ['required', 'integer', 'min:1', 'max:500'],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'website.max' => 'Invalid submission.',
            'phone.regex' => 'Please enter a valid phone number.',
            'email.unique' => 'This email address has already been submitted.',
            'phone.unique' => 'This phone number has already been submitted.',
        ];
    }
}
