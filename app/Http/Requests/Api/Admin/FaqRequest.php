<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class FaqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required'],
            'sort_order' => ['required', 'integer'],
        ];
    }
}
