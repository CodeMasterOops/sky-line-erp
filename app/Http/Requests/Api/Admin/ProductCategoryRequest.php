<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'parent_id' => ['nullable', Rule::exists('product_categories', 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'thumbnail_image' => ['nullable', 'string', 'max:255'],
            'banner_image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('product_categories')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('product_categories')->withoutTrashed()->ignore($this->product_category)],
            ])
        };
    }
}
