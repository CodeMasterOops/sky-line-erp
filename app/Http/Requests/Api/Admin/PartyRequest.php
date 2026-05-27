<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\PartyTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\AmountOrPercentDiscountTypeEnum;

class PartyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'type' => ['required', Rule::enum(PartyTypeEnum::class)],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', Rule::enum(AmountOrPercentDiscountTypeEnum::class)],
            'discount_value' => ['nullable', 'numeric', 'min:0'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'code' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()],
                'phone' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()],
                'email' => ['nullable', 'email', TRule::unique('parties')->withoutTrashed()],
                'pan' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'code' => ['required', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()->ignore($this->party)],
                'phone' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()->ignore($this->party)],
                'email' => ['nullable', 'email', TRule::unique('parties')->withoutTrashed()->ignore($this->party)],
                'pan' => ['nullable', 'string', 'max:255', TRule::unique('parties')->withoutTrashed()->ignore($this->party)],
            ])
        };
    }
}
