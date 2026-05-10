<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ChequeBounceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'remarks' => ['nullable', 'string'],
        ];
    }
}
