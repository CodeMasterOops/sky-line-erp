<?php

/**
 * Default roles provisioned for every new company.
 * Permissions must match strings from #[Permissions(...)] attributes in Api/Admin controllers.
 */
return [

    [
        'name' => 'Administrator',
        'permissions' => ['*'],
    ],

    [
        'name' => 'Account Manager',
        'permissions' => [
            'account', 'account_group', 'account_report', 'account_setting', 'accounting_period',
            'approve_journal_voucher', 'approve_payment', 'approve_payment_voucher',
            'approve_receipt', 'approve_receipt_voucher',
            'balance_sheet', 'bank_reconciliation', 'books_health', 'budget',
            'cash_flow', 'close_fiscal_year', 'create_account',
            'journal_voucher', 'payment', 'payment_voucher', 'receipt', 'receipt_voucher',
            'recurring_journal', 'tds_receivable', 'tds_report', 'trial_balance',
            'unbalanced_journals', 'unposted_documents', 'update_account_setting', 'vat_report',
            'ap_aging', 'ar_aging', 'ar_ap_reconciliation',
            'show_journal_voucher', 'show_payment', 'show_payment_voucher',
            'show_receipt', 'show_receipt_voucher', 'show_tax',
        ],
    ],

    [
        'name' => 'Sales Executive',
        'permissions' => [
            'approve_credit_note', 'approve_customer_advance', 'approve_delivery_challan',
            'approve_invoice', 'approve_quotation', 'approve_sales_order',
            'apply_customer_advance',
            'credit_note', 'debit_note',
            'delivery_challan', 'invoice', 'quotation', 'sales_order',
            'receipt', 'approve_receipt',
            'show_credit_note', 'show_debit_note', 'show_delivery_challan',
            'show_invoice', 'show_party', 'show_quotation', 'show_receipt', 'show_sales_order',
            'view_crm_customer_360', 'view_customer_advance',
            'void_credit_note', 'void_invoice', 'void_receipt',
            'write_off_invoice', 'write_off_batch',
            'ar_aging', 'yearly_sales_report', 'category_wise_sales_report',
            'supplier_wise_purchase_report',
        ],
    ],

    [
        'name' => 'Purchase Executive',
        'permissions' => [
            'approve_bill', 'approve_debit_note', 'approve_grn',
            'approve_payment', 'approve_purchase_order',
            'bill', 'debit_note', 'grn', 'purchase_order',
            'payment', 'approve_payment',
            'show_bill', 'show_debit_note', 'show_grn',
            'show_party', 'show_payment', 'show_purchase_order',
            'void_bill', 'void_debit_note', 'void_payment',
            'ap_aging', 'yearly_purchase_report', 'category_wise_purchase_report',
        ],
    ],

    [
        'name' => 'Inventory Manager',
        'permissions' => [
            'approve_damage_report', 'approve_opening_stock_entry',
            'approve_stock_adjustment', 'approve_stock_transfer',
            'attribute', 'batch', 'bom',
            'brand', 'category',
            'damage_report', 'opening_stock_entry',
            'show_product', 'show_product_category', 'show_warehouse',
            'show_stock_adjustment', 'show_stock_transfer',
            'stock_adjustment', 'stock_transfer',
            'unit', 'unit_conversion',
            'view_serial_number', 'warehouse',
            'stock_aging', 'production_order', 'show_production_order',
        ],
    ],

    [
        'name' => 'HR Manager',
        'permissions' => [
            'attendance',
            'show_department', 'show_designation', 'show_employee',
            'show_holiday', 'show_leave_application', 'show_leave_type',
            'show_payroll', 'show_salary_component', 'show_salary_structure',
            'show_work_schedule',
            'employee', 'payroll',
        ],
    ],

    [
        'name' => 'CRM Executive',
        'permissions' => [
            'assign_crm_lead', 'convert_crm_lead',
            'show_crm_lead', 'show_party',
            'view_crm_customer_360', 'view_crm_report', 'view_crm_timeline',
        ],
    ],

    [
        'name' => 'Cashier',
        'permissions' => [
            'receipt', 'approve_receipt', 'show_receipt',
            'payment', 'approve_payment', 'show_payment',
            'show_invoice', 'cheque', 'show_cheque',
        ],
    ],

    [
        'name' => 'Viewer',
        'permissions' => [
            'show_bill', 'show_cheque', 'show_credit_note', 'show_crm_lead',
            'show_damage_report', 'show_debit_note', 'show_delivery_challan',
            'show_department', 'show_designation', 'show_employee',
            'show_expense', 'show_fixed_asset', 'show_grn', 'show_holiday',
            'show_invoice', 'show_journal_voucher', 'show_leave_application',
            'show_leave_type', 'show_opening_stock_entry', 'show_party',
            'show_payment', 'show_payment_mode', 'show_payment_voucher',
            'show_payroll', 'show_product', 'show_product_category',
            'show_production_order', 'show_purchase_order', 'show_quotation',
            'show_receipt', 'show_receipt_voucher', 'show_recurring_journal',
            'show_role', 'show_salary_component', 'show_salary_structure',
            'show_sales_order', 'show_stock_adjustment', 'show_stock_transfer',
            'show_tax', 'show_unit', 'show_unit_conversion', 'show_user',
            'show_warehouse', 'show_work_schedule',
        ],
    ],

];
