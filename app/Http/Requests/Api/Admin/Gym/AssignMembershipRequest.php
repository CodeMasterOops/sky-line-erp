<?php

namespace App\Http\Requests\Api\Admin\Gym;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AssignMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', TRule::exists('members', 'id')],
            'membership_plan_id' => ['required', 'integer', TRule::exists('membership_plans', 'id')],
            'start_date' => ['nullable', 'date'],
            // Left out entirely, price and joining fee come from the plan.
            'price' => ['nullable', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'joining_fee' => ['nullable', 'numeric', 'min:0'],
            'create_invoice' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
