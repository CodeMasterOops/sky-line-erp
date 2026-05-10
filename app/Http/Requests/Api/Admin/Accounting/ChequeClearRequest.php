<?php

namespace App\Http\Requests\Api\Admin\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class ChequeClearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cleared_date' => ['required', 'date'],
        ];
    }
}
