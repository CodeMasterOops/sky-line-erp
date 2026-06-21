<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Models\Batch;
use App\Enums\BatchStatusEnum;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->has('status')) {
                return;
            }

            $batch = $this->route('batch');
            $target = BatchStatusEnum::tryFrom((string) $this->input('status'));

            if (! $batch instanceof Batch || $target === null) {
                return;
            }

            $current = $batch->status instanceof BatchStatusEnum
                ? $batch->status
                : BatchStatusEnum::tryFrom((string) $batch->status);

            if ($current !== null && ! $current->canTransitionTo($target)) {
                $validator->errors()->add('status', __('Cannot change batch status from :from to :to.', [
                    'from' => $current->label(),
                    'to' => $target->label(),
                ]));
            }
        });
    }
}
