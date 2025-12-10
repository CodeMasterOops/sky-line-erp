<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'name' => ['required', 'string', 'max:255'],
            'thumbnail_image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('brands')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('brands')->withoutTrashed()->ignore($this->brand)],
            ])
        };
    }
}
