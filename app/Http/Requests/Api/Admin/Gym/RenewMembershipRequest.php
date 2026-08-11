<?php

namespace App\Http\Requests\Api\Admin\Gym;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class RenewMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Omitted, the renewal keeps the plan the member is already on —
            // supplying one is how an upgrade or downgrade happens.
            'membership_plan_id' => ['nullable', 'integer', TRule::exists('membership_plans', 'id')],
            'start_date' => ['nullable', 'date'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'create_invoice' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
