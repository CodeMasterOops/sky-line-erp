<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class BrandRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('brands')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('brands')->withoutTrashed()],
            ],
            'PUT' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('brands')->withoutTrashed()->ignore($this->brand)],
                'code' => ['required', 'string', 'max:255', TRule::unique('brands')->withoutTrashed()->ignore($this->brand)],
            ],
        };
    }
}
