<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Rules\WarehouseIsStockLocation;
use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bom_id' => ['required', 'integer', 'exists:boms,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id', new WarehouseIsStockLocation],
            'planned_qty' => ['required', 'numeric', 'min:0.0001'],
            'planned_start' => ['nullable', 'date'],
            'planned_end' => ['nullable', 'date', 'after_or_equal:planned_start'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
