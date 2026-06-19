<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Enums\BatchStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'batch_no' => ['sometimes', 'string', 'max:100'],
            'lot_no' => ['nullable', 'string', 'max:100'],
            'mfg_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:mfg_date'],
            'status' => ['sometimes', Rule::in(array_column(BatchStatusEnum::cases(), 'value'))],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
