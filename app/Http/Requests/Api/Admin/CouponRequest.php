<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date_format:Y-m-d H:i'],
            'end_date' => ['required', 'date_format:Y-m-d H:i', 'after_or_equal:start_date'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount' => ['required', 'numeric'],
            'max_discount_amount' => ['nullable', 'numeric'],
            'usage_limit' => ['nullable', 'integer'],
            'same_user_limit' => ['nullable', 'integer'],
            'products' => ['required', 'array'],
            'products.*' => ['required', Rule::exists('products', 'id')->withoutTrashed()],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'code' => ['required', Rule::unique('coupons')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'code' => ['required', Rule::unique('coupons')->withoutTrashed()->ignore($this->coupon)],
            ])
        };
    }
}
