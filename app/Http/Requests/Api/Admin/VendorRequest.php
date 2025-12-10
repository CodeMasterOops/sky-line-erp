<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'vendor_name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable'],
            'address' => ['nullable'],
            'user_name' => ['required', 'string', 'max:255'],
            'user_phone' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('vendors', 'slug')->withoutTrashed()],
                'email' => ['required', 'email', Rule::unique('vendors', 'email')->withoutTrashed()],
                'user_email' => ['required', 'email', Rule::unique('vendor_users', 'email')],
                'password' => ['nullable', 'min:7', 'confirmed'],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('vendors', 'slug')->withoutTrashed()->ignore($this->vendor)],
                'email' => ['required', 'email', Rule::unique('vendors', 'email')->withoutTrashed()->ignore($this->vendor)],
                'user_email' => ['required', 'email', Rule::unique('vendor_users', 'email')->ignore($this->vendor->admin)],
            ])
        };
    }
}
