<?php

namespace App\Http\Requests\Api\Admin;

use App\Enums\StatusEnum;
use App\Tenancy\TRule;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class JournalVoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reference_no' => ['nullable', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', Rule::in([StatusEnum::DRAFT->value, StatusEnum::APPROVED->value])],
            'items' => ['required', 'array', 'min:2'],
            'items.*.account_id' => ['required', TRule::exists('accounts', 'id')->withoutTrashed()],
            'items.*.dr_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.cr_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $items = $this->input('items', []);
            $totalDr = 0;
            $totalCr = 0;

            foreach ($items as $index => $item) {
                $dr = (float) ($item['dr_amount'] ?? 0);
                $cr = (float) ($item['cr_amount'] ?? 0);

                if (($dr > 0 && $cr > 0) || ($dr <= 0 && $cr <= 0)) {
                    $validator->errors()->add("items.$index", 'Each line must have either Dr or Cr amount.');
                }

                $totalDr += $dr;
                $totalCr += $cr;
            }

            if ($totalDr <= 0 || $totalCr <= 0 || abs($totalDr - $totalCr) > 0.0001) {
                $validator->errors()->add('items', 'Total Dr amount must be equal to Total Cr amount.');
            }
        });
    }
}
