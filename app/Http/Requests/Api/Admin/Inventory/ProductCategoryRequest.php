<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Models\ProductCategory;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }

    public function rules(): array
    {
        $parentRules = [
            'nullable',
            'integer',
            TRule::exists('product_categories', 'id')->withoutTrashed(),
        ];

        $nameUnique = $this->scopedNameUniqueRule();

        return match ($this->method()) {
            'POST' => [
                'parent_id' => $parentRules,
                'name' => ['required', 'string', 'max:255', $nameUnique],
                'description' => ['nullable'],
            ],
            'PUT' => [
                'parent_id' => $parentRules,
                'name' => ['required', 'string', 'max:255', $this->scopedNameUniqueRule(ignore: $this->product_category)],
                'description' => ['nullable'],
            ],
        };
    }

    private function scopedNameUniqueRule(?ProductCategory $ignore = null): string
    {
        $rule = TRule::unique('product_categories')->withoutTrashed();

        $parentId = $this->input('parent_id');
        if ($parentId === null || $parentId === '') {
            $rule = $rule->whereNull('parent_id');
        } else {
            $rule = $rule->where('parent_id', (int) $parentId);
        }

        if ($ignore) {
            $rule = $rule->ignore($ignore);
        }

        return (string) $rule;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            if ($parentId === null || $parentId === '') {
                return;
            }

            $parentId = (int) $parentId;

            if (! $this->isMethod('PUT') || ! $this->product_category) {
                return;
            }

            $categoryId = (int) $this->product_category->id;

            if ($parentId === $categoryId) {
                $validator->errors()->add('parent_id', __('A category cannot be its own parent.'));

                return;
            }

            $currentId = $parentId;
            $guard = 0;
            while ($currentId !== null && $guard++ < 1000) {
                if ($currentId === $categoryId) {
                    $validator->errors()->add('parent_id', __('Invalid parent: would create a circular reference.'));

                    return;
                }

                $row = ProductCategory::query()->whereKey($currentId)->first(['id', 'parent_id']);
                $currentId = $row && $row->parent_id !== null ? (int) $row->parent_id : null;
            }
        });
    }
}
