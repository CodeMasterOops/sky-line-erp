<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class AttributeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'name' => ['required', 'string', 'max:255'],
            'values' => ['required', 'array'],
            'values.*' => ['required', 'array'],
            'values.*.value' => ['required', 'string', 'max:255'],
            'values.*.sort_order' => ['required', 'integer'],
        ];

        return match ($this->method()) {
            'POST' => $validations,
            'PUT' => array_merge($validations, [
                'values.*.id' => ['nullable', Rule::exists('attribute_values', 'id')->where('attribute_id', $this->attribute->id)->withoutTrashed()],
            ])
        };
    }
}
