<?php

namespace App\Http\Requests\Api\Admin;

use App\Tenancy\TRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_sales_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'bank_sales_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'cash_purchase_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'bank_purchase_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'vat_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'advance_tax_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'sales_discount_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'purchase_discount_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'customer_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'supplier_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'employee_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'other_contact_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'purchase_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'sales_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'inventory_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
            'cogs_account_id' => ['nullable', 'integer', TRule::exists('accounts', 'id')->withoutTrashed()],
        ];
    }
}
