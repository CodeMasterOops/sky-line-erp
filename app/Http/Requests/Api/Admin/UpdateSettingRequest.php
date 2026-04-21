<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Validation\Rule;
use App\Enums\InventoryCostingMethodEnum;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $company = auth('admin')->user()->company;

        return [
            'company_name' => ['required', 'string', 'max:255'],
            'legal_name' => ['required', 'string', 'max:255'],
            'pan' => ['nullable'],
            'logo' => ['nullable', 'image'],
            'phone' => ['nullable'],
            'landline' => ['nullable'],
            'website' => ['nullable'],
            'address' => ['nullable'],
            'code' => ['nullable', Rule::unique('companies')->withoutTrashed()->ignore($company)],
            'email' => ['required', 'email', Rule::unique('companies', 'email')->withoutTrashed()->ignore($company)],
            'fiscal_year_id' => ['required', Rule::exists('fiscal_years', 'id')->withoutTrashed()],
            'inventory_costing_method' => ['required', Rule::enum(InventoryCostingMethodEnum::class)],
        ];
    }
}
