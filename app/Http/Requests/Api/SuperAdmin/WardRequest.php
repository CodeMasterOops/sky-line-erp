<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Models\Palika;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class WardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'palika_id' => ['required', 'integer', Rule::exists(Palika::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
