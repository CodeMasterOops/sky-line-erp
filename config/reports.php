<?php

/*
|--------------------------------------------------------------------------
| Report Catalogue
|--------------------------------------------------------------------------
|
| The single source of truth for the Reports hub. Every entry names the SPA
| route that opens it, the permission that guards it, and the module that owns
| the data behind it.
|
| `GET /api/admin/report-catalogue` serves this filtered by permission AND by
| the company's enabled modules, so the hub never offers a report whose route
| the module guard would bounce or whose endpoint would answer 403. Before this
| existed the list lived in ReportsHub.vue and knew about permissions only —
| see docs/module-capping-and-advanced-handling-plan.md gap A3.
|
| The `module` key must match the route's `meta.module` in
| resources/js/router/modules/*.routes.js: that meta is what the router guard
| enforces, and a catalogue that disagrees with it produces exactly the dead
| navigation this file exists to prevent. Where a report's *screen* lives in
| one module's route file but its *data* comes from another module's endpoints
| (IRD sync, TDS challan), both the route meta and this tag name the module
| that owns the endpoint. tests/Feature/Modules/ModuleCappingSurfaceTest.php
| asserts every module named here exists in config/modules.php.
|
| Entry shape:
|   'label'      string  Display name in the hub.
|   'route'      string  Vue route name.
|   'permission' string  Permission required (from PermissionRegistry::all()).
|   'module'     string  Owning module key, or null for core.
|
*/

return [

    'categories' => [

        // Revenue — most checked daily.
        [
            'title' => 'Sales',
            'slug' => 'sales',
            'description' => 'Sales performance by period, item, category, customer, profit, tax, and discount.',
            'icon' => 'ti ti-shopping-cart',
            'accent_class' => 'is-teal',
            'items' => [
                ['label' => 'Sales Report', 'route' => 'admin.sales-report', 'permission' => 'list_sales_order', 'module' => 'sales'],
                ['label' => 'Sales By Item', 'route' => 'admin.sales-by-item', 'permission' => 'list_sales_order', 'module' => 'sales'],
                ['label' => 'Sales Summary', 'route' => 'admin.sales-summary-report', 'permission' => 'sales_summary_report', 'module' => 'sales'],
                ['label' => 'Daily Sales', 'route' => 'admin.daily-sales-report', 'permission' => 'daily_sales_report', 'module' => 'sales'],
                ['label' => 'Monthly Sales', 'route' => 'admin.monthly-sales-report', 'permission' => 'monthly_sales_report', 'module' => 'sales'],
                ['label' => 'Yearly Sales', 'route' => 'admin.yearly-sales-report', 'permission' => 'yearly_sales_report', 'module' => 'sales'],
                ['label' => 'Customer Wise Sales', 'route' => 'admin.customer-wise-sales-report', 'permission' => 'customer_wise_sales_report', 'module' => 'sales'],
                ['label' => 'Category Wise Sales', 'route' => 'admin.category-wise-sales-report', 'permission' => 'category_wise_sales_report', 'module' => 'sales'],
                ['label' => 'Product Wise Sales', 'route' => 'admin.product-wise-sales-report', 'permission' => 'product_wise_sales_report', 'module' => 'sales'],
                ['label' => 'Sales Return', 'route' => 'admin.sales-return-report', 'permission' => 'sales_return_report', 'module' => 'sales'],
                ['label' => 'Outstanding Sales', 'route' => 'admin.outstanding-sales-report', 'permission' => 'outstanding_sales_report', 'module' => 'sales'],
                ['label' => 'Sales Tax', 'route' => 'admin.sales-tax-report', 'permission' => 'sales_tax_report', 'module' => 'sales'],
                ['label' => 'Sales Profit', 'route' => 'admin.sales-profit-report', 'permission' => 'sales_profit_report', 'module' => 'sales'],
                ['label' => 'Discount Report', 'route' => 'admin.discount-report', 'permission' => 'discount_report', 'module' => 'sales'],
                ['label' => 'Sales Ledger', 'route' => 'admin.sales-ledger-report', 'permission' => 'sales_ledger_report', 'module' => 'sales'],
            ],
        ],

        // Cost control.
        [
            'title' => 'Purchase',
            'slug' => 'purchase',
            'description' => 'Purchase reports by period, item, supplier, category, GRN, tax, and pending orders.',
            'icon' => 'ti ti-truck-delivery',
            'accent_class' => 'is-amber',
            'items' => [
                ['label' => 'Purchase Report', 'route' => 'admin.purchase-report', 'permission' => 'list_bill', 'module' => 'purchase'],
                ['label' => 'Purchase By Item', 'route' => 'admin.purchase-by-item', 'permission' => 'list_bill', 'module' => 'purchase'],
                ['label' => 'Purchase Summary', 'route' => 'admin.purchase-summary-report', 'permission' => 'purchase_summary_report', 'module' => 'purchase'],
                ['label' => 'Daily Purchase', 'route' => 'admin.daily-purchase-report', 'permission' => 'daily_purchase_report', 'module' => 'purchase'],
                ['label' => 'Monthly Purchase', 'route' => 'admin.monthly-purchase-report', 'permission' => 'monthly_purchase_report', 'module' => 'purchase'],
                ['label' => 'Yearly Purchase', 'route' => 'admin.yearly-purchase-report', 'permission' => 'yearly_purchase_report', 'module' => 'purchase'],
                ['label' => 'Supplier Wise Purchase', 'route' => 'admin.supplier-wise-purchase-report', 'permission' => 'supplier_wise_purchase_report', 'module' => 'purchase'],
                ['label' => 'Category Wise Purchase', 'route' => 'admin.category-wise-purchase-report', 'permission' => 'category_wise_purchase_report', 'module' => 'purchase'],
                ['label' => 'Purchase Return', 'route' => 'admin.purchase-return-report', 'permission' => 'purchase_return_report', 'module' => 'purchase'],
                ['label' => 'Outstanding Purchase', 'route' => 'admin.outstanding-purchase-report', 'permission' => 'outstanding_purchase_report', 'module' => 'purchase'],
                ['label' => 'Purchase Tax', 'route' => 'admin.purchase-tax-report', 'permission' => 'purchase_tax_report', 'module' => 'purchase'],
                ['label' => 'Purchase Ledger', 'route' => 'admin.purchase-ledger-report', 'permission' => 'purchase_ledger_report', 'module' => 'purchase'],
                ['label' => 'GRN Report', 'route' => 'admin.grn-report', 'permission' => 'grn_report', 'module' => 'purchase'],
                ['label' => 'Pending Purchase', 'route' => 'admin.pending-purchase-report', 'permission' => 'pending_purchase_report', 'module' => 'purchase'],
                ['label' => 'Purchase Discount', 'route' => 'admin.purchase-discount-report', 'permission' => 'purchase_discount_report', 'module' => 'purchase'],
            ],
        ],

        // Core financials.
        [
            'title' => 'Accounting',
            'slug' => 'accounting',
            'description' => 'Core financial statements — trial balance, P&L, balance sheet, ledgers, and vouchers.',
            'icon' => 'ti ti-calculator',
            'accent_class' => 'is-blue',
            'items' => [
                ['label' => 'Trial Balance', 'route' => 'admin.trial-balance', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Profit & Loss', 'route' => 'admin.profit-and-loss', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Balance Sheet', 'route' => 'admin.balance-sheet', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Cash Flow', 'route' => 'admin.cash-flow', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Expense Statement', 'route' => 'admin.expense-statement-report', 'permission' => 'expense_statement_report', 'module' => 'accounting'],
                ['label' => 'General Ledger', 'route' => 'admin.general-ledger', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Cash Ledger', 'route' => 'admin.cash-ledger-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Bank Ledger', 'route' => 'admin.bank-ledger-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Customer Ledger', 'route' => 'admin.sales-ledger-report', 'permission' => 'sales_ledger_report', 'module' => 'sales'],
                ['label' => 'Supplier Ledger', 'route' => 'admin.purchase-ledger-report', 'permission' => 'purchase_ledger_report', 'module' => 'purchase'],
                ['label' => 'Journal Report', 'route' => 'admin.journal-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Payment Voucher Report', 'route' => 'admin.payment-voucher-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Receipt Voucher Report', 'route' => 'admin.receipt-voucher-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'All Voucher Register', 'route' => 'admin.all-voucher-register', 'permission' => 'list_account', 'module' => 'accounting'],
            ],
        ],

        // Receivables.
        [
            'title' => 'Customer',
            'slug' => 'customer',
            'description' => 'Customer balances, ledgers, outstanding amounts, ageing, and transaction history.',
            'icon' => 'ti ti-users',
            'accent_class' => 'is-green',
            'items' => [
                ['label' => 'Customer Ledger', 'route' => 'admin.sales-ledger-report', 'permission' => 'sales_ledger_report', 'module' => 'sales'],
                ['label' => 'Customer Outstanding / Ageing', 'route' => 'admin.ar-aging', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Customer Transaction', 'route' => 'admin.customer-transaction-report', 'permission' => 'sales_ledger_report', 'module' => 'sales'],
                ['label' => 'Customer Statement', 'route' => 'admin.customer-statement', 'permission' => 'sales_ledger_report', 'module' => 'sales'],
                ['label' => 'Top Customers', 'route' => 'admin.customer-wise-sales-report', 'permission' => 'customer_wise_sales_report', 'module' => 'sales'],
            ],
        ],

        // Payables.
        [
            'title' => 'Supplier',
            'slug' => 'supplier',
            'description' => 'Supplier balances, ledgers, outstanding amounts, ageing, and transaction history.',
            'icon' => 'ti ti-building-store',
            'accent_class' => 'is-orange',
            'items' => [
                ['label' => 'Supplier Ledger', 'route' => 'admin.purchase-ledger-report', 'permission' => 'purchase_ledger_report', 'module' => 'purchase'],
                ['label' => 'Supplier Outstanding / Ageing', 'route' => 'admin.ap-aging', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Supplier Transaction', 'route' => 'admin.supplier-transaction-report', 'permission' => 'purchase_ledger_report', 'module' => 'purchase'],
                ['label' => 'Supplier Statement', 'route' => 'admin.supplier-statement', 'permission' => 'purchase_ledger_report', 'module' => 'purchase'],
                ['label' => 'Top Suppliers', 'route' => 'admin.supplier-wise-purchase-report', 'permission' => 'supplier_wise_purchase_report', 'module' => 'purchase'],
            ],
        ],

        // Liquidity.
        [
            'title' => 'Cash & Bank',
            'slug' => 'cash-bank',
            'description' => 'Track cash and bank movements, reconcile statements, and monitor cheques.',
            'icon' => 'ti ti-cash',
            'accent_class' => 'is-teal',
            'items' => [
                ['label' => 'Cash Book', 'route' => 'admin.cash-ledger-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Bank Book', 'route' => 'admin.bank-ledger-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Bank Reconciliation', 'route' => 'admin.bank-reconciliation', 'permission' => 'list_account', 'module' => 'banking'],
                ['label' => 'Daily Collection', 'route' => 'admin.daily-collection-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Daily Payment', 'route' => 'admin.daily-payment-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Cheque Issue', 'route' => 'admin.cheque-issue-report', 'permission' => 'list_account', 'module' => 'banking'],
                ['label' => 'Cheque Receive', 'route' => 'admin.cheque-receive-report', 'permission' => 'list_account', 'module' => 'banking'],
            ],
        ],

        // Stock.
        [
            'title' => 'Inventory',
            'slug' => 'inventory',
            'description' => 'Stock levels, movement, valuation, aging, warehouse locations, expiry, and batch tracking.',
            'icon' => 'ti ti-packages',
            'accent_class' => 'is-cyan',
            'items' => [
                ['label' => 'Inventory Valuation', 'route' => 'admin.inventory-valuation', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Stock Aging', 'route' => 'admin.stock-aging', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Reorder Alerts', 'route' => 'admin.reorder-alerts', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Stock Movement Report', 'route' => 'admin.stock-movement-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Stock Ledger Report', 'route' => 'admin.stock-ledger-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Warehouse Wise Stock', 'route' => 'admin.warehouse-stock-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Warehouse Transfer Report', 'route' => 'admin.warehouse-transfer-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Expiry Stock Report', 'route' => 'admin.expiry-stock-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Dead Stock Report', 'route' => 'admin.dead-stock-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Stock Opening Report', 'route' => 'admin.stock-opening-report', 'permission' => 'list_product', 'module' => 'inventory'],
                ['label' => 'Inventory Summary Report', 'route' => 'admin.inventory-summary-report', 'permission' => 'inventory_summary_report', 'module' => 'inventory'],
                ['label' => 'Damage Stock Report', 'route' => 'admin.damage-stock-report', 'permission' => 'list_damage_report', 'module' => 'inventory'],
                ['label' => 'Production Variance Report', 'route' => 'admin.production-variance-report', 'permission' => 'list_production_order', 'module' => 'manufacturing'],
                ['label' => 'Batch Stock Report', 'route' => 'admin.batch-stock-report', 'permission' => 'list_batch', 'module' => 'inventory'],
                ['label' => 'Batch Traceability Report', 'route' => 'admin.batch-traceability-report', 'permission' => 'list_batch', 'module' => 'inventory'],
            ],
        ],

        // Compliance. The VAT registers and the TDS report read `account-report/*`
        // (accounting); the TDS challan reads `nepal/tds/*` (nepal-compliance).
        [
            'title' => 'Tax & Statutory',
            'slug' => 'tax-statutory',
            'description' => 'VAT returns, sales and purchase registers, TDS reports, and tax challans.',
            'icon' => 'ti ti-file-certificate',
            'accent_class' => 'is-mint',
            'items' => [
                ['label' => 'VAT Return (D3)', 'route' => 'admin.vat-return', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Bikri Khata (Sales Register)', 'route' => 'admin.vat-sales-register', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'Kharid Khata (Purchase Register)', 'route' => 'admin.vat-purchase-register', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'TDS Report', 'route' => 'admin.tds-report', 'permission' => 'list_account', 'module' => 'accounting'],
                ['label' => 'TDS Challan & Certificate', 'route' => 'admin.tds-challan', 'permission' => 'list_account', 'module' => 'nepal-compliance'],
            ],
        ],

        // People.
        [
            'title' => 'HR & Payroll',
            'slug' => 'hr-payroll',
            'description' => 'Payroll summaries, attendance records, leave balances, and salary TDS.',
            'icon' => 'ti ti-users-group',
            'accent_class' => 'is-violet',
            'items' => [
                ['label' => 'Payroll Summary', 'route' => 'admin.hr-report-payroll', 'permission' => 'list_payroll', 'module' => 'payroll'],
                ['label' => 'Attendance Report', 'route' => 'admin.hr-report-attendance', 'permission' => 'list_attendance', 'module' => 'hr'],
                ['label' => 'Leave Balance', 'route' => 'admin.hr-report-leave', 'permission' => 'list_leave_application', 'module' => 'hr'],
                ['label' => 'TDS Salary Report', 'route' => 'admin.hr-report-tds-salary', 'permission' => 'list_payroll', 'module' => 'hr'],
            ],
        ],

        // Integrations — least used.
        [
            'title' => 'System',
            'slug' => 'system',
            'description' => 'Integration health and sync status with government and external systems.',
            'icon' => 'ti ti-cloud-data-connection',
            'accent_class' => 'is-slate',
            'items' => [
                ['label' => 'IRD EBS Sync Status', 'route' => 'admin.ird-sync', 'permission' => 'list_account', 'module' => 'nepal-compliance'],
            ],
        ],

    ],

];
