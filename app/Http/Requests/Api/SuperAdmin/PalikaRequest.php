<?php

namespace App\Http\Requests\Api\SuperAdmin;

use App\Models\District;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class PalikaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'district_id' => ['required', 'integer', Rule::exists(District::class, 'id')],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
