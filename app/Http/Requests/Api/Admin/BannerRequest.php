<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'image' => ['required', 'string', 'max:255'],
            'link' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable'],
            'sort_order' => ['required', 'integer'],
        ];
    }
}
