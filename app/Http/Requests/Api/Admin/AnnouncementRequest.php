<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:255'],
            'link' => ['required', 'string', 'max:255'],
            'sort_order' => ['required', 'integer'],
        ];
    }
}
