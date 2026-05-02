<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('product_categories')->withoutTrashed()],
                'description' => ['nullable'],
            ],
            'PUT' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('product_categories')->withoutTrashed()->ignore($this->product_category)],
                'description' => ['nullable'],
            ],
        };
    }
}
