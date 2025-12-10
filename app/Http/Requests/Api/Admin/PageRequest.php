<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'title' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'meta_title' => ['nullable'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
            'is_page' => ['nullable', 'boolean'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('pages')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('pages')->withoutTrashed()->ignore($this->page)],
            ])
        };
    }
}
