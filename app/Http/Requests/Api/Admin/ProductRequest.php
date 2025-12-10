<?php

namespace App\Http\Requests\Api\Admin;

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
            'vendor_id' => ['required', Rule::exists('vendors', 'id')->withoutTrashed()],
            'name' => ['required', 'string', 'max:255'],
            'thumbnail_image' => ['nullable', 'string', 'max:255'],
            'brand_id' => ['nullable', Rule::exists('brands', 'id')->withoutTrashed()],
            'specification' => ['nullable'],
            'ingredients' => ['nullable'],
            'description' => ['nullable'],
            'meta_title' => ['nullable'],
            'meta_keywords' => ['nullable'],
            'meta_description' => ['nullable'],
            'is_active' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => [Rule::exists('tags', 'id')->withoutTrashed()],
            'categories' => ['required', 'array'],
            'categories.*' => [Rule::exists('product_categories', 'id')->withoutTrashed()],
            'has_variants' => ['nullable', 'boolean'],
            'variants' => ['required', 'array'],
            'variants.*.sku' => ['nullable'],
            'variants.*.price' => ['nullable', 'numeric'],
            'variants.*.sales_price' => ['required', 'numeric'],
            'variants.*.weight' => ['nullable', 'numeric'],
            'variants.*.length' => ['nullable', 'numeric'],
            'variants.*.width' => ['nullable', 'numeric'],
            'variants.*.height' => ['nullable', 'numeric'],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attribute_values' => ['nullable', 'array'],
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => [Rule::exists('attribute_values', 'id')->withoutTrashed()],
            'images' => ['nullable', 'array'],
            'images.*.title' => ['nullable', 'string', 'max:255'],
            'images.*.image' => ['required', 'string', 'max:255'],
            'images.*.image_alt' => ['nullable', 'string', 'max:255'],
            'images.*.sort_order' => ['required', 'integer'],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('products')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'slug' => ['required', 'alpha_dash', Rule::unique('products')->withoutTrashed()->ignore($this->product)],
            ])
        };
    }
}
