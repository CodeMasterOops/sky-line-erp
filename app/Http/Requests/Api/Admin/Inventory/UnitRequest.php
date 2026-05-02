<?php

namespace App\Http\Requests\Api\Admin\Inventory;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('units')->withoutTrashed()],
                'code' => ['required', 'string', 'max:255', TRule::unique('units')->withoutTrashed()],
            ],
            'PUT' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('units')->withoutTrashed()->ignore($this->unit)],
                'code' => ['required', 'string', 'max:255', TRule::unique('units')->withoutTrashed()->ignore($this->unit)],
            ],
        };
    }
}
