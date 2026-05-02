<?php

namespace App\Http\Resources\Admin\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cash_sales_account_id' => $this->cash_sales_account_id ?? '',
            'bank_sales_account_id' => $this->bank_sales_account_id ?? '',
            'cash_purchase_account_id' => $this->cash_purchase_account_id ?? '',
            'bank_purchase_account_id' => $this->bank_purchase_account_id ?? '',
            'vat_account_id' => $this->vat_account_id ?? '',
            'advance_tax_account_id' => $this->advance_tax_account_id ?? '',
            'sales_discount_account_id' => $this->sales_discount_account_id ?? '',
            'purchase_discount_account_id' => $this->purchase_discount_account_id ?? '',
            'customer_account_id' => $this->customer_account_id ?? '',
            'supplier_account_id' => $this->supplier_account_id ?? '',
            'employee_account_id' => $this->employee_account_id ?? '',
            'other_contact_account_id' => $this->other_contact_account_id ?? '',
            'purchase_account_id' => $this->purchase_account_id ?? '',
            'sales_account_id' => $this->sales_account_id ?? '',
            'inventory_account_id' => $this->inventory_account_id ?? '',
            'cogs_account_id' => $this->cogs_account_id ?? '',
        ];
    }
}
