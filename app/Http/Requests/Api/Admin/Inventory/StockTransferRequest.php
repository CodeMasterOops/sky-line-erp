<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Rules\WithinActiveFiscalYear;
use App\Services\Inventory\BatchGuard;
use App\Rules\WarehouseIsStockLocation;
use App\Rules\WarehouseInAccessibleBranch;
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
            'from_warehouse_id' => ['nullable', new WarehouseInAccessibleBranch, new WarehouseIsStockLocation],
            'to_warehouse_id' => ['required', new WarehouseInAccessibleBranch, new WarehouseIsStockLocation],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.from_warehouse_id' => ['required_without:from_warehouse_id', 'nullable', 'integer', 'different:to_warehouse_id', new WarehouseInAccessibleBranch, new WarehouseIsStockLocation],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];

        return match ($this->method()) {
            'POST', 'PUT' => $rules,
            default => $rules,
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            BatchGuard::validateItems(
                $validator,
                $this->input('items', []),
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn (array $item) => $item['from_warehouse_id'] ?? $this->input('from_warehouse_id'),
            );
        });
    }
}
