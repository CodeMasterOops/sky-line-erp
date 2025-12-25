<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
            'PUT' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()->ignore($this->warehouse)],
                'code' => ['required', 'string', 'max:255', TRule::unique('warehouses')->withoutTrashed()->ignore($this->warehouse)],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:255'],
            ],
        };
    }
}
