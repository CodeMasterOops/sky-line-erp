<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Enums\BillingCycleEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'billing_cycle' => ['sometimes', Rule::enum(BillingCycleEnum::class)],
            'trial_ends_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
