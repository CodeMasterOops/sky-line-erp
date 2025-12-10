<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class CollectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'description' => ['nullable'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
            'products' => ['nullable', 'array'],
            'products.*' => ['required', Rule::exists('products', 'id')->withoutTrashed()],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('collections')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('collections')->withoutTrashed()->ignore($this->collection)],
            ])
        };
    }
}
