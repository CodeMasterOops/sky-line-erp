<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BlogCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'title' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'meta_title' => ['nullable'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('blog_categories')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('blog_categories')->withoutTrashed()->ignore($this->blog_category)],
            ])
        };
    }
}
