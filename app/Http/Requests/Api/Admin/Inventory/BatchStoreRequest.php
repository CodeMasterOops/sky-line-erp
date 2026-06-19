<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class BatchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'batch_no' => ['required', 'string', 'max:100'],
            'lot_no' => ['nullable', 'string', 'max:100'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:mfg_date'],
            'initial_qty' => ['required', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
