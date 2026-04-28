<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use App\Models\Warehouse;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('parent_id') && $this->input('parent_id') === '') {
            $this->merge(['parent_id' => null]);
        }
    }

    public function rules(): array
    {
        $parentRules = [
            'nullable',
            'integer',
            TRule::exists('warehouses', 'id')->withoutTrashed(),
        ];

        return match ($this->method()) {
            'POST' => [
                'parent_id' => $parentRules,
                'name' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
            'PUT' => [
                'parent_id' => $parentRules,
                'name' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()->ignore($this->warehouse)],
                'code' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()->ignore($this->warehouse)],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
        };
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $parentId = $this->input('parent_id');
            if ($parentId === null || $parentId === '') {
                return;
            }

            $parentId = (int) $parentId;

            if (! $this->isMethod('PUT') || ! $this->warehouse) {
                return;
            }

            $warehouseId = (int) $this->warehouse->id;

            if ($parentId === $warehouseId) {
                $validator->errors()->add('parent_id', __('A warehouse cannot be its own parent.'));

                return;
            }

            $currentId = $parentId;
            $guard = 0;
            while ($currentId !== null && $guard++ < 1000) {
                if ($currentId === $warehouseId) {
                    $validator->errors()->add('parent_id', __('Invalid parent: would create a circular reference.'));

                    return;
                }

                $row = Warehouse::query()->whereKey($currentId)->first(['id', 'parent_id']);
                $currentId = $row && $row->parent_id !== null ? (int) $row->parent_id : null;
            }
        });
    }
}
