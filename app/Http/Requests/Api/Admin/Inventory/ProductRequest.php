<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Models\Tax;
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

    protected function prepareForValidation(): void
    {
        $minStockLevel = $this->input('min_stock_level');
        if ($minStockLevel === null || $minStockLevel === '') {
            $this->merge(['min_stock_level' => 0]);
        }

        if ($this->input('product_type') === ProductTypeEnum::SERVICE->value) {
            $this->merge([
                'brand_id' => null,
                'min_stock_level' => 0,
                'reorder_quantity' => 0,
                'has_variants' => false,
            ]);

            $variants = $this->input('variants', []);
            if (is_array($variants)) {
                foreach ($variants as $i => $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $variants[$i]['sku'] = null;
                    if (! isset($row['purchase_price']) || $row['purchase_price'] === '') {
                        $variants[$i]['purchase_price'] = 0;
                    }
                }
                $this->merge(['variants' => $variants]);
            }
        }
    }

    public function rules(): array
    {
        $validations = [
            'product_category_id' => ['required', TRule::exists('product_categories', 'id')->withoutTrashed()],
            'product_type' => ['required', Rule::enum(ProductTypeEnum::class)],
            'image' => ['nullable', 'image'],
            'unit_id' => ['required', TRule::exists('units', 'id')->withoutTrashed()],
            'brand_id' => [
                'nullable',
                TRule::exists('brands', 'id')->withoutTrashed(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('product_type') === ProductTypeEnum::SERVICE->value && $value !== null && $value !== '') {
                        $fail(__('Services cannot have a brand.'));
                    }
                },
            ],
            'hsn_code' => ['nullable', 'string', 'max:255'],
            'min_stock_level' => [
                'nullable',
                'numeric',
                'min:0',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('product_type') === ProductTypeEnum::SERVICE->value
                        && $value !== null
                        && $value !== ''
                        && (float) $value > 0) {
                        $fail(__('Services cannot have a minimum stock level.'));
                    }
                },
            ],
            'tax_id' => [
                'nullable',
                TRule::exists('taxes', 'id')->withoutTrashed(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! Tax::query()->whereKey($value)->lineItem()->exists()) {
                        $fail(__('The selected tax must be a VAT rate.'));
                    }
                },
            ],
            'reorder_quantity' => ['nullable', 'integer'],
            'description' => ['nullable'],
            'has_variants' => [
                'nullable',
                'boolean',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->input('product_type') === ProductTypeEnum::SERVICE->value && $this->boolean('has_variants')) {
                        $fail(__('Services cannot have variants.'));
                    }
                },
            ],
            'variants' => [
                'required',
                'array',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_array($value)) {
                        return;
                    }
                    if ($this->input('product_type') === ProductTypeEnum::SERVICE->value && count($value) !== 1) {
                        $fail(__('A service must have exactly one price row.'));
                    }
                    if ($this->input('product_type') === ProductTypeEnum::PRODUCT->value && $this->boolean('has_variants')) {
                        foreach ($value as $i => $row) {
                            $ids = $row['attribute_values'] ?? [];
                            if (! is_array($ids) || $ids === []) {
                                $fail(__('Each variant must include option values (row :row).', ['row' => $i + 1]));

                                return;
                            }
                        }
                    }
                },
            ],
            'variants.*.sku' => ['nullable'],
            'variants.*.sales_price' => ['required', 'numeric'],
            'variants.*.purchase_price' => [
                Rule::requiredIf(fn () => $this->input('product_type') !== ProductTypeEnum::SERVICE->value),
                'nullable',
                'numeric',
                'min:0',
            ],
            'variants.*.is_default' => ['nullable', 'boolean'],
            'variants.*.attribute_values' => ['nullable', 'array'],
            'attribute_values' => ['nullable', 'array'],
            'attribute_values.*' => [Rule::exists('attribute_values', 'id')->withoutTrashed()],
        ];

        return match ($this->method()) {
            'POST' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
                'code' => ['nullable', 'string', 'max:255', TRule::unique('products')->withoutTrashed()],
            ]),
            'PUT' => array_merge($validations, [
                'name' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
                'code' => ['required', 'string', 'max:255', TRule::unique('products')->withoutTrashed()->ignore($this->product)],
                'variants.*.id' => [
                    'nullable',
                    'integer',
                    Rule::exists('product_variants', 'id')->where(fn ($query) => $query->where(
                        'product_id',
                        $this->route('product')->id
                    )),
                ],
            ])
        };
    }
}
