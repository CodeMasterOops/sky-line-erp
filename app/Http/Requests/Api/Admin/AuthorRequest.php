<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AuthorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'photo' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email'],
            'designation' => ['nullable', 'string', 'max:255'],
        ];
    }
}
