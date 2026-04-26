<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class StockTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'reference_no' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'from_warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'to_warehouse_id' => ['required', 'different:from_warehouse_id', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.from_bin_id' => [
                'required',
                'integer',
                TRule::exists('bins', 'id')->withoutTrashed()->where('warehouse_id', (string) $this->input('from_warehouse_id')),
            ],
            'items.*.to_bin_id' => [
                'required',
                'integer',
                TRule::exists('bins', 'id')->withoutTrashed()->where('warehouse_id', (string) $this->input('to_warehouse_id')),
            ],
        ];

        return match ($this->method()) {
            'POST', 'PUT' => $rules,
            default => $rules,
        };
    }
}
