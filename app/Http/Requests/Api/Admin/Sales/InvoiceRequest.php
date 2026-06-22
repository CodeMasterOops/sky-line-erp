<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Enums\TaxTypeEnum;
use App\Enums\TaxLineTypeEnum;
use Illuminate\Validation\Rule;
use App\Http\Validation\ProductLineRules;
use Illuminate\Foundation\Http\FormRequest;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_no' => ['nullable', 'string', 'max:255'],
            'bijak_no' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }
                    $user = auth('admin')->user();
                    $company = $user->company;
                    $query = \App\Models\Invoice::withoutGlobalScopes()
                        ->where('bijak_no', $value)
                        ->where('company_id', $company->id)
                        ->where('fiscal_year_id', $company->fiscal_year_id)
                        ->whereNull('deleted_at');
                    if ($this->route('invoice')) {
                        $query->where('id', '!=', $this->route('invoice')->id);
                    }
                    if ($query->exists()) {
                        $fail('The bijak no has already been used for another invoice in this fiscal year.');
                    }
                },
            ],
            'invoice_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $fy = auth('admin')->user()?->company?->fiscalYear;
                    if (! $fy) {
                        return;
                    }
                    $date = \Carbon\Carbon::parse($value)->toDateString();
                    if ($date < $fy->start_date->toDateString() || $date > $fy->end_date->toDateString()) {
                        $fail("The invoice date must be within the active fiscal year ({$fy->start_date->format('d M Y')} – {$fy->end_date->format('d M Y')}).");
                    }
                },
            ],
            'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'quotation_id' => [
                'nullable',
                TRule::exists('quotations', 'id')->withoutTrashed(),
                function (string $_attribute, mixed $value, \Closure $fail): void {
                    if (! $value) {
                        return;
                    }
                    $quotation = \App\Models\Quotation::withoutGlobalScopes()->find($value);
                    if ($quotation && $quotation->expiry_date && $quotation->expiry_date < now()->toDateString()) {
                        $fail(__('The selected quotation has expired and cannot be converted to an invoice.'));
                    }
                },
            ],
            'sales_order_id' => ['nullable', TRule::exists('sales_orders', 'id')->withoutTrashed()],
            'reference_type' => ['nullable', 'string', 'max:255', 'required_with:reference_id'],
            'reference_id' => ['nullable', 'integer', 'min:1', 'required_with:reference_type'],
            'remarks' => ['nullable', 'string'],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.delivery_challan_item_id' => ['nullable', Rule::exists('delivery_challan_items', 'id')],
            'items.*.warehouse_id' => ProductLineRules::warehouseId(),
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => [
                'nullable',
                TRule::exists('taxes', 'id')->withoutTrashed(),
                function ($_attribute, $value, $fail) {
                    if ($value === null) {
                        return;
                    }
                    $tax = \App\Models\Tax::withoutGlobalScopes()->find($value);
                    if ($tax && $tax->type === TaxTypeEnum::TDS) {
                        $fail('TDS taxes cannot be applied to invoice lines. TDS is a withholding tax deducted at payment time.');
                    }
                },
            ],
            'items.*.tax_group_id' => [
                'nullable',
                TRule::exists('tax_groups', 'id')->withoutTrashed(),
            ],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_line_type' => ['nullable', Rule::enum(TaxLineTypeEnum::class)],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            \App\Services\Inventory\BatchGuard::validateItems(
                $validator,
                $this->input('items', []),
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn (array $item) => $item['warehouse_id'] ?? null,
            );
        });

        $validator->after(function ($validator) {
            // IRD: bijak_no is mandatory for B2B taxable transactions above Rs 50,000.
            if ($this->filled('bijak_no')) {
                return;
            }

            $partyId = $this->input('party_id');
            if (! $partyId) {
                return;
            }

            $items = $this->input('items', []);
            $taxableTotal = collect($items)->sum(function ($item) {
                if (($item['tax_line_type'] ?? '') !== 'taxable') {
                    return 0;
                }

                $lineTotal = ((float) ($item['quantity'] ?? 0)) * ((float) ($item['rate'] ?? 0));

                return $lineTotal - (float) ($item['discount_amount'] ?? 0);
            });

            if ($taxableTotal >= 50000) {
                $validator->errors()->add(
                    'bijak_no',
                    'Bijak number (tax invoice serial) is required for B2B taxable transactions of Rs 50,000 or above (Nepal VAT Act).'
                );
            }
        });
    }
}
