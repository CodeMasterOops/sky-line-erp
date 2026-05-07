<?php

/**
 * Default provisioning for a new company after COA import (config/coa.php).
 * Account codes must match account `code` values created by CoaInsertService.
 */
return [

    'fiscal_year' => [
        /**
         * Calendar year (Jan 1 – Dec 31) to create when no matching fiscal year exists.
         * Null = use the current year at runtime.
         */
        'year' => null,
    ],

    'default_warehouse' => [
        'name' => 'Main',
        'code' => 'MAIN',
    ],

    'default_payment_modes' => [
        ['name' => 'Cash', 'is_active' => true],
        ['name' => 'Bank Transfer', 'is_active' => true],
    ],

    /**
     * Maps AccountSetting column => ledger account `code` in `accounts` for the company.
     * Employee/Other contact use the closest available placeholder ledgers in the default COA.
     */
    'account_setting_codes' => [
        'cash_sales_account_id' => 'CIH',
        'bank_sales_account_id' => 'AB',
        'cash_purchase_account_id' => 'CIH',
        'bank_purchase_account_id' => 'AB',
        'vat_account_id' => 'VA',
        'advance_tax_account_id' => 'AT',
        'sales_discount_account_id' => 'LSD',
        'purchase_discount_account_id' => 'LPD',
        'customer_account_id' => 'SD',
        'supplier_account_id' => 'SC',
        'employee_account_id' => 'SP2',
        'other_contact_account_id' => 'OR',
        'purchase_account_id' => 'PA',
        'sales_account_id' => 'SOG',
        'inventory_account_id' => 'INV',
        'cogs_account_id' => 'COGS',
    ],
    'default_branch' => [
        'name' => 'Main',
        'code' => 'MAIN',
    ],

];
