<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use App\Enums\ShippingRegionEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateShippingSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vendors' => ['required', 'array'],
            'vendors.*.vendor_id' => ['nullable', Rule::exists('vendors', 'id')->withoutTrashed()],
            'vendors.*.regions' => ['required', 'array'],
            'vendors.*.regions.*.region' => ['required', Rule::enum(ShippingRegionEnum::class)],
            'vendors.*.regions.*.fixed_price' => ['nullable', 'numeric'],
            'vendors.*.regions.*.free_shipping_over_quantity' => ['nullable', 'integer'],
            'vendors.*.regions.*.free_shipping_over_amount' => ['nullable', 'numeric'],
            'vendors.*.regions.*.is_free_shipping' => ['nullable', 'boolean'],
            'vendors.*.regions.*.ship_to_default_region_only' => ['nullable', 'boolean'],
        ];
    }
}
