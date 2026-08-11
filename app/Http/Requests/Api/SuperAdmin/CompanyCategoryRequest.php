<?php

namespace App\Http\Requests\Api\SuperAdmin;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Services\Modules\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;

class CompanyCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('companyCategory')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('company_categories', 'slug')->ignore($categoryId),
            ],
            'description' => ['nullable', 'string'],
            'icon' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(app(ModuleRegistry::class)->keys())],
        ];
    }

    public function messages(): array
    {
        return [
            'modules.*.in' => 'One of the selected modules does not exist.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('slug') && $this->filled('name')) {
            $this->merge(['slug' => Str::slug($this->input('name'))]);
        }
    }
}
