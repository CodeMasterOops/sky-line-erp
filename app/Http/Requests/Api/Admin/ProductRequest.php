<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\ProductTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $validations = [
            'product_category_id' => ['required', TRule::exists('product_categories', 'id')->withoutTrashed()],
            'product_type' => ['required', Rule::enum(ProductTypeEnum::class)],
            'image' => ['nullable', 'image'],
            'unit_id' => ['required', TRule::exists('units', 'id')->withoutTrashed()],
            'brand_id' => ['nullable', TRule::exists('brands', 'id')->withoutTrashed()],
            'reorder_quantity' => ['nullable', 'integer'],
            'description' => ['nullable'],
            'has_variants' => ['nullable', 'boolean'],
            'variants' => ['required', 'array'],
            'variants.*.sku' => ['nullable'],
            'variants.*.sales_price' => ['required', 'numeric'],
            'variants.*.purchase_price' => ['required', 'numeric'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attribute_values' => ['nullable', 'array'],
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => [Rule::exists('attribute_values', 'id')->withoutTrashed()],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
                'code' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
            ])
        };
    }
}
