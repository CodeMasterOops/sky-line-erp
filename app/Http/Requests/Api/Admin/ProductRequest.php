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
            'sales_price' => ['required', 'numeric'],
            'purchase_price' => ['required', 'numeric'],
            'reorder_quantity' => ['nullable', 'integer'],
            'description' => ['nullable'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
                'sku' => ['nullable', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
                'code' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
                'sku' => ['nullable', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
            ])
        };
    }
}
