<?php

namespace App\Http\Requests\Api\Admin\Sales;

use App\Tenancy\TRule;
use App\Enums\StatusEnum;
use App\Models\CreditNote;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;
use App\Services\Inventory\BatchGuard;
use App\Http\Validation\ProductLineRules;
use Illuminate\Foundation\Http\FormRequest;

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
            'credit_note_date' => [
                'required',
                'date',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $fy = auth('admin')->user()?->company?->fiscalYear;
                    if (! $fy) {
                        return;
                    }
                    $date = \Carbon\Carbon::parse($value)->toDateString();
                    if ($date < $fy->start_date->toDateString() || $date > $fy->end_date->toDateString()) {
                        $fail("The credit note date must be within the active fiscal year ({$fy->start_date->format('d M Y')} – {$fy->end_date->format('d M Y')}).");
                    }
                },
            ],
            'party_id' => ['required', TRule::exists('parties', 'id')->withoutTrashed()],
            'invoice_id' => ['nullable', TRule::exists('invoices', 'id')->withoutTrashed()],
            'remarks' => ['nullable', 'string'],
            'order_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'order_discount_value' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variant_id' => ['required', TRule::exists('product_variants', 'id')->withoutTrashed()],
            'items.*.warehouse_id' => ProductLineRules::warehouseId(),
            'items.*.unit_id' => ['nullable', TRule::exists('units', 'id')->withoutTrashed()],
            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $index = explode('.', $attribute)[1];
                    $invoiceItemId = $this->input("items.{$index}.invoice_item_id");

                    if (! $invoiceItemId) {
                        return;
                    }

                    $invoiceItem = DB::table('invoice_items')
                        ->where('id', $invoiceItemId)
                        ->whereNull('deleted_at')
                        ->first(['quantity']);

                    if (! $invoiceItem) {
                        return;
                    }

                    $originalQty = (int) $invoiceItem->quantity;

                    // Exclude the current credit note when validating an update so
                    // its own lines are not counted against the remaining allowance.
                    $currentCreditNoteId = $this->route('creditNote') instanceof CreditNote
                        ? $this->route('creditNote')->id
                        : null;

                    $alreadyReturned = (int) DB::table('credit_note_items')
                        ->join('credit_notes', 'credit_notes.id', '=', 'credit_note_items.credit_note_id')
                        ->where('credit_note_items.invoice_item_id', $invoiceItemId)
                        ->where('credit_notes.status', StatusEnum::APPROVED->value)
                        ->whereNull('credit_notes.voided_at')
                        ->whereNull('credit_notes.deleted_at')
                        ->whereNull('credit_note_items.deleted_at')
                        ->when($currentCreditNoteId, fn ($q) => $q->where('credit_notes.id', '!=', $currentCreditNoteId))
                        ->sum('credit_note_items.quantity');

                    $remainingReturnable = max(0, $originalQty - $alreadyReturned);

                    if ((int) $value > $remainingReturnable) {
                        $fail(__(
                            'Return quantity (:requested) exceeds the remaining returnable quantity of :remaining (original: :original, already returned: :returned).',
                            [
                                'requested' => (int) $value,
                                'remaining' => $remainingReturnable,
                                'original' => $originalQty,
                                'returned' => $alreadyReturned,
                            ]
                        ));
                    }
                },
            ],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
            'items.*.line_discount_type' => ['nullable', Rule::in(['fixed', 'percent'])],
            'items.*.line_discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*.tax_id' => ['nullable', TRule::exists('taxes', 'id')->withoutTrashed()],
            'items.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.batch_id' => ['nullable', 'integer', TRule::exists('batches', 'id')],
            'items.*.invoice_item_id' => [
                'nullable',
                'integer',
                function (string $_attribute, mixed $value, \Closure $fail): void {
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
        $validator->after(function ($validator) {
            BatchGuard::validateItems(
                $validator,
                $this->input('items', []),
                (int) (auth('admin')->user()?->company?->id ?? 0),
                fn (array $item) => $item['warehouse_id'] ?? null,
            );
        });
    }
}
