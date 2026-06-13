<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\MultiTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountSetting extends Model
{
    use Auditable;
    use MultiTenant;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'cash_sales_account_id',
        'bank_sales_account_id',
        'cash_purchase_account_id',
        'bank_purchase_account_id',
        'vat_account_id',
        'advance_tax_account_id',
        'sales_discount_account_id',
        'purchase_discount_account_id',
        'customer_account_id',
        'supplier_account_id',
        'employee_account_id',
        'other_contact_account_id',
        'purchase_account_id',
        'grni_account_id',
        'sales_account_id',
        'inventory_account_id',
        'cogs_account_id',
        'retained_earnings_account_id',
        'opening_stock_equity_account_id',
        'tds_receivable_account_id',
    ];
}
