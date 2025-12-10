<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'blog_category_id' => ['required', Rule::exists('blog_categories', 'id')->withoutTrashed()],
            'title' => ['required', 'string', 'max:255'],
            'sub_title' => ['nullable', 'string', 'max:255'],
            'publish_date' => ['required', 'date'],
            'featured_image' => ['nullable', 'string', 'max:255'],
            'authors' => ['required', 'array'],
            'authors.*' => [Rule::exists('authors', 'id')->withoutTrashed()],
            'description' => ['nullable'],
            'short_description' => ['nullable'],
            'meta_title' => ['nullable'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('blogs')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('blogs')->withoutTrashed()->ignore($this->blog)],
            ])
        };
    }
}
