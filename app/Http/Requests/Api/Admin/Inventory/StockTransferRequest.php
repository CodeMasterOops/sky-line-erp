<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use App\Rules\WithinActiveFiscalYear;
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
            'date' => ['required', 'date', new WithinActiveFiscalYear],
            'from_warehouse_id' => ['nullable', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'to_warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.from_warehouse_id' => ['required_without:from_warehouse_id', 'nullable', 'integer', 'different:to_warehouse_id', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];

        return match ($this->method()) {
            'POST', 'PUT' => $rules,
            default => $rules,
        };
    }
}
