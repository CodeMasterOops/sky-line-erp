<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use App\Enums\LandedCostTreatmentEnum;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\LandedCostAllocationMethodEnum;

class GoodsReceivedNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['nullable', TRule::exists('purchase_orders', 'id')->withoutTrashed()],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'received_date' => ['required', 'date'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.purchase_order_item_id' => ['nullable', TRule::exists('purchase_order_items', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.ordered_qty' => ['nullable', 'numeric', 'min:0'],
            'items.*.received_qty' => ['required', 'numeric', 'min:0.001'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.batch_no' => ['nullable', 'string'],
            'items.*.expiry_date' => ['nullable', 'date'],
            'items.*.serial_nos' => ['nullable', 'array'],
            'items.*.serial_nos.*' => ['string', 'max:100'],
            'landed_costs' => ['nullable', 'array'],
            'landed_costs.*.cost_type' => ['required_with:landed_costs', 'string', 'max:100'],
            'landed_costs.*.description' => ['nullable', 'string', 'max:255'],
            'landed_costs.*.treatment' => ['nullable', Rule::in(array_column(LandedCostTreatmentEnum::cases(), 'value'))],
            'landed_costs.*.allocation_method' => ['nullable', Rule::in(array_column(LandedCostAllocationMethodEnum::cases(), 'value'))],
            'landed_costs.*.amount' => ['required_with:landed_costs', 'numeric', 'min:0'],
            'landed_costs.*.vat_amount' => ['nullable', 'numeric', 'min:0'],
            'landed_costs.*.vat_claimable_amount' => ['nullable', 'numeric', 'min:0'],
            'landed_costs.*.account_id' => ['nullable', TRule::exists('accounts', 'id')->withoutTrashed()],
        ];
    }
}
