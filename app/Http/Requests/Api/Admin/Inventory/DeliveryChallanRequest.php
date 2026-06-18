<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Http\Validation\ProductLineRules;
use Illuminate\Foundation\Http\FormRequest;

class DeliveryChallanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sales_order_id' => ['nullable', TRule::exists('sales_orders', 'id')->withoutTrashed()],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'challan_date' => ['required', 'date'],
            'delivery_address' => ['nullable', 'string'],
            'receiver_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,approved'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                TRule::exists('product_variants', 'id')->withoutTrashed(),
                ...ProductLineRules::physicalProductVariantId(),
            ],
            'items.*.sales_order_item_id' => ['nullable', TRule::exists('sales_order_items', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];
    }
}
