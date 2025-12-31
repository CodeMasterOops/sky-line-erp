<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class TaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return match ($this->method()) {
            'POST' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('taxes')->withoutTrashed()],
                'rate' => ['required', 'numeric'],
            ],
            'PUT' => [
                'name' => ['required', 'string', 'max:255', TRule::unique('taxes')->withoutTrashed()->ignore($this->tax)],
                'code' => ['required', 'numeric'],
            ],
        };
    }
}
