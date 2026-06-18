<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ProductionOrderCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'produced_qty' => ['required', 'numeric', 'min:0.0001'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.id' => ['required', 'integer', 'exists:production_order_consumptions,id'],
            'consumptions.*.consumed_qty' => ['required', 'numeric', 'min:0'],
            'consumptions.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ];
    }
}
