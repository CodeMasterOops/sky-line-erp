<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Enums\BillingCycleEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AssignSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_cycle' => ['required', Rule::enum(BillingCycleEnum::class)],
            'trial_ends_at' => ['nullable', 'date', 'after:now'],
            'ends_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
