<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class FileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['required', Rule::exists('folders', 'id')->withoutTrashed()],
            'files' => ['required', 'array'],
            'files.*' => ['mimes:jpg,jpeg,png,svg,webp,pdf'],
        ];
    }
}
