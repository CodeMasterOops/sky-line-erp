<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSidebarPinnedLinksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'links' => ['present', 'array', 'max:5'],
            'links.*' => ['required', 'string', 'distinct', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'links.present' => 'The pinned links list is required.',
            'links.array' => 'The pinned links must be a list.',
            'links.max' => 'You may pin up to :max links.',
            'links.*.distinct' => 'Each link may only be pinned once.',
        ];
    }
}
