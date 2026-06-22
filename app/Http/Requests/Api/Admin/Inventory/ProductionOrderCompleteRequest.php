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
            // Defective output for this batch — expensed to manufacturing variance, not stocked.
            'scrap_qty' => ['nullable', 'numeric', 'min:0'],
            // When false, records this batch and keeps the order open for further completions.
            // Defaults to true (single-shot completion) for backward compatibility.
            'close' => ['nullable', 'boolean'],
            'consumptions' => ['nullable', 'array'],
            'consumptions.*.id' => ['required', 'integer', 'exists:production_order_consumptions,id'],
            'consumptions.*.consumed_qty' => ['required', 'numeric', 'min:0'],
            'consumptions.*.batch_id' => ['nullable', 'integer', 'exists:batches,id'],
        ];
    }
}
