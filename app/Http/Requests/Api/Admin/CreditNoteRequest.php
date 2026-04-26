<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\Requests\Concerns\ValidatesDocumentLineBins;

class CreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'credit_note_no' => ['nullable', 'string', 'max:255'],
            'credit_note_date' => ['required', 'date'],
            'party_id' => ['nullable', TRule::exists('parties', 'id')->withoutTrashed()],
            'invoice_id' => ['nullable', TRule::exists('invoices', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.warehouse_id' => ['required', TRule::exists('warehouses', 'id')->withoutTrashed()],
            'items.*.bin_id' => ['required', 'integer', TRule::exists('bins', 'id')->withoutTrashed()],
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.invoice_item_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $invoiceId = $this->input('invoice_id');
                    if (! $invoiceId) {
                        $fail(__('An invoice must be selected when referencing an invoice line.'));

                        return;
                    }
                    $exists = DB::table('invoice_items')
                        ->where('id', $value)
                        ->where('invoice_id', $invoiceId)
                        ->whereNull('deleted_at')
                        ->exists();
                    if (! $exists) {
                        $fail(__('The selected invoice line is invalid for this invoice.'));
                    }
                },
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            ValidatesDocumentLineBins::eachBinBelongsToThatLinesWarehouse(
                $v,
                (array) $this->input('items', []),
            );
        });
    }
}
