<?php

namespace App\Http\Requests\Api\Admin\Purchase;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Enums\TaxLineTypeEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use App\Rules\WithinActiveFiscalYear;
use App\Enums\LandedCostTreatmentEnum;
use App\Services\Inventory\BatchGuard;
use App\Http\Validation\ProductLineRules;
use App\Http\Validation\BranchScopedExists;
use Illuminate\Foundation\Http\FormRequest;
use App\Enums\LandedCostAllocationMethodEnum;

class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bill_no' => ['nullable', 'string', 'max:255'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:100'],
            'bill_date' => ['required', 'date', new WithinActiveFiscalYear],
            'due_date' => ['nullable', 'date', 'after_or_equal:bill_date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'purchase_order_id' => ['nullable', TRule::exists('purchase_orders', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => [
                'required',
                TRule::exists('product_variants', 'id')->withoutTrashed(),
                ...ProductLineRules::physicalProductVariantId(__('Services cannot be purchased on bills. Use expenses for service payments.')),
            ],
            'items.*.warehouse_id' => ProductLineRules::warehouseId(),
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => [
                'nullable',
                TRule::exists('taxes', 'id')->withoutTrashed(),
                function ($attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $tax = \App\Models\Tax::withoutGlobalScopes()->find($value);
                    if ($tax && $tax->type === TaxTypeEnum::TDS) {
                        $fail('TDS taxes cannot be applied to bill lines. TDS is a withholding tax deducted at payment time.');
                    }
                },
            ],
            'items.*.tax_group_id' => ['nullable', TRule::exists('tax_groups', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_line_type' => ['nullable', Rule::enum(TaxLineTypeEnum::class)],
            'items.*.grn_item_id' => ['nullable', 'integer', BranchScopedExists::child('grn_items', 'goods_received_note_id', 'goods_received_notes')],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')->withoutTrashed()],
            'items.*.batch_no' => ['nullable', 'string', 'max:255'],
            'items.*.mfg_date' => ['nullable', 'date'],
            'items.*.expiry_date' => ['nullable', 'date'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $orderType = $this->input('order_discount_type', 'fixed');
            $ov = $this->input('order_discount_value');
            if ($orderType === 'percent' && $ov !== null && (float) $ov > 100) {
                $v->errors()->add('order_discount_value', 'Order discount may not be greater than 100%.');
            }

            foreach ($this->input('items', []) as $i => $item) {
                $t = $item['line_discount_type'] ?? 'fixed';
                if ($t === 'percent' && isset($item['line_discount_value']) && (float) $item['line_discount_value'] > 100) {
                    $v->errors()->add("items.$i.line_discount_value", 'Line discount may not be greater than 100%.');
                }
            }

            $hasGrnLines = collect($this->input('items', []))
                ->contains(fn (array $item): bool => ! empty($item['grn_item_id']));

            if ($hasGrnLines && ! empty($this->input('landed_costs'))) {
                $v->errors()->add('landed_costs', 'Additional charges cannot be added when billing GRN lines.');
            }

            // Batch selection only applies to direct receipts. GRN-sourced lines
            // carry their batch from the already-approved GRN, so exclude them.
            $directItems = array_filter(
                $this->input('items', []),
                fn (array $item): bool => empty($item['grn_item_id']),
            );

            BatchGuard::validateItems(
                $v,
                $directItems,
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn (array $item) => $item['warehouse_id'] ?? null,
            );
        });
    }
}
