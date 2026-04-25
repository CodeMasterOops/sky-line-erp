<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryStockReconciliationAlignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'strategy' => ['required', 'string', Rule::in(['valued_to_stock', 'stock_to_valued'])],
            'product_variant_id' => ['required', 'integer'],
            'warehouse_id' => ['required', 'integer'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
