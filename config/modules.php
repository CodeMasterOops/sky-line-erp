<?php

/*
|--------------------------------------------------------------------------
| Module Registry
|--------------------------------------------------------------------------
|
| Phase 0 of the Modular SaaS Platform plan
| (docs/saas-modular-platform-and-gym-module-plan.md).
|
| This file is the single source of truth for *what a module is*. Module
| definitions are code-coupled — they name route files, permissions, activators
| and provisioning steps — so they live in config, not the database. Only the
| per-company *state* (which modules a company has switched on) lives in the DB,
| from Phase 1 onwards.
|
| Modules are compile-time present and runtime-gated: disabling a module never
| removes code or data, it only closes the doors (routes, menus, jobs, catalogue
| entries). See §3.1 of the plan.
|
| tests/Feature/Modules/ModuleRegistryTest.php enforces the invariants:
|   - every enforced #[Permissions] string is owned by exactly one module
|   - `requires` graphs are acyclic and reference real modules
|   - referenced route files, activators and provisioning steps exist
|
| Definition shape (every key optional except `name` and `group`):
|
|   'name'                   string   Human label shown in the Super Admin UI.
|   'group'                  string   core | foundation | optional | industry.
|   'description'            string   One-line summary for the module matrix.
|   'icon'                   string   Tabler icon class, matching sidebar.json.
|   'always_on'              bool     Cannot be disabled (the `core` floor).
|   'self_service'           bool     A tenant admin may toggle it themselves.
|   'requires'               list     Module keys that must be enabled with it.
|   'conflicts'              list     Module keys that cannot be enabled with it.
|   'permissions'            list     Permission strings this module OWNS. Used to
|                                     filter the role-editor catalogue; roles keep
|                                     stored permissions while a module is off.
|   'route_files'            list     Files under routes/modules/ gated in whole.
|   'route_groups'           map      file => marker, for modules whose routes live
|                                     inside another module's file (or api_admin.php)
|                                     and must be wrapped in an inline
|                                     Route::middleware('module:{key}') group.
|   'frontend_key'           string   Value used by `meta.module` and sidebar.json.
|   'provisioning_steps'     list     ModuleAwareStep classes run when enabled.
|   'activator'              ?string  ModuleActivator class (onEnable / onDisable).
|   'scheduled_commands'     list     Commands that must skip companies without it.
|   'data_transfer_entities' list     Import/export entities to de-register when off.
|   'settings_schema'        map      Default per-company module settings.
|   'sort_order'             int      Display order inside its group.
|
| The `industry` group holds the vertical modules. `gym` is the first; adding
| the next one follows the checklist in §10.4 of the plan.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Core — always on
    |--------------------------------------------------------------------------
    | Company, branches, users, roles, parties, taxes, payment modes, settings,
    | notifications, dashboard, profile and address reference. Every other module
    | assumes these exist, so `core` carries no requirements of its own and can
    | never be switched off.
    */
    'core' => [
        'name' => 'Core',
        'group' => 'core',
        'description' => 'Company, branches, users, roles, parties, taxes and settings.',
        'icon' => 'ti ti-building',
        'always_on' => true,
        'self_service' => false,
        'requires' => [],
        'permissions' => [
            'create_branch', 'create_party', 'create_payment_mode', 'create_role', 'create_tax', 'create_user',
            'delete_branch', 'delete_party', 'delete_payment_mode', 'delete_role', 'delete_tax', 'delete_user',
            'edit_branch', 'edit_party', 'edit_payment_mode', 'edit_role', 'edit_tax', 'edit_user', 'list_branch',
            'list_currency', 'list_party', 'list_payment_mode', 'list_role', 'list_setting', 'list_tax',
            'list_user', 'show_branch', 'show_party', 'show_payment_mode', 'show_role', 'show_tax', 'show_user',
            'update_setting', 'update_user_status',
        ],
        'route_files' => ['api_settings.php', 'api_user_management.php'],
        'frontend_key' => 'core',
        'scheduled_commands' => ['sanctum:prune-expired', 'app:check-orphan-rows'],
        'sort_order' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Foundation — togglable, but most companies keep them
    |--------------------------------------------------------------------------
    */

    'accounting' => [
        'name' => 'Accounting',
        'group' => 'foundation',
        'description' => 'Chart of accounts, vouchers, ledgers and financial statements.',
        'icon' => 'ti ti-calculator',
        'requires' => [],
        'permissions' => [
            'ap_aging', 'approve_journal_voucher', 'approve_payment_voucher', 'approve_receipt_voucher',
            'ar_aging', 'ar_ap_reconciliation', 'balance_sheet', 'books_health', 'cash_flow', 'close_fiscal_year',
            'create_account', 'create_account_group', 'create_accounting_period', 'create_bank_account',
            'create_journal_voucher', 'create_payment_voucher', 'create_receipt_voucher',
            'create_recurring_journal', 'delete_account', 'delete_account_group', 'delete_journal_voucher',
            'delete_payment_voucher', 'delete_receipt_voucher', 'delete_recurring_journal', 'edit_account',
            'edit_account_group', 'edit_accounting_period', 'edit_journal_voucher', 'edit_payment_voucher',
            'edit_receipt_voucher', 'edit_recurring_journal', 'expense_statement_report', 'general_ledger',
            'inventory_gl_reconciliation', 'inventory_valuation', 'journal_report', 'list_account',
            'list_account_group', 'list_account_setting', 'list_accounting_period', 'list_bank_account',
            'list_journal_voucher', 'list_payment_voucher', 'list_receipt_voucher', 'list_recurring_journal',
            'profit_loss', 'reorder_alerts', 'repost_document', 'show_account', 'show_account_group',
            'show_journal_voucher', 'show_payment_voucher', 'show_receipt_voucher', 'show_recurring_journal',
            'stock_aging', 'tds_report', 'trial_balance', 'unbalanced_journals', 'unposted_documents',
            'update_account_setting', 'vat_report', 'view_cash_flow_forecast',
        ],
        'route_files' => ['api_accounting.php'],
        'frontend_key' => 'accounting',
        'data_transfer_entities' => ['account', 'journal'],
        'sort_order' => 10,
    ],

    'inventory' => [
        'name' => 'Inventory',
        'group' => 'foundation',
        'description' => 'Products, warehouses, stock movements, batches and inventory reports.',
        'icon' => 'ti ti-packages',
        'requires' => [],
        'permissions' => [
            'approve_damage_report', 'approve_delivery_challan', 'approve_grn', 'approve_opening_stock_entry',
            'approve_stock_adjustment', 'approve_stock_transfer', 'create_attribute', 'create_batch',
            'create_brand', 'create_damage_report', 'create_delivery_challan', 'create_grn',
            'create_opening_stock_entry', 'create_product', 'create_product_category', 'create_stock_adjustment',
            'create_stock_transfer', 'create_unit', 'create_unit_conversion', 'create_warehouse',
            'delete_attribute', 'delete_brand', 'delete_damage_report', 'delete_delivery_challan', 'delete_grn',
            'delete_opening_stock_entry', 'delete_product', 'delete_product_category', 'delete_stock_adjustment',
            'delete_stock_transfer', 'delete_unit', 'delete_unit_conversion', 'delete_warehouse',
            'dispatch_stock_transfer', 'edit_attribute', 'edit_batch', 'edit_brand', 'edit_damage_report',
            'edit_delivery_challan', 'edit_grn', 'edit_opening_stock_entry', 'edit_product',
            'edit_product_category', 'edit_stock_adjustment', 'edit_stock_transfer', 'edit_unit',
            'edit_unit_conversion', 'edit_warehouse', 'inventory_batch_stock_report',
            'inventory_batch_traceability_report', 'inventory_dead_stock_report', 'inventory_expiry_stock_report',
            'inventory_stock_ledger_report', 'inventory_stock_movement_report', 'inventory_stock_opening_report',
            'inventory_summary_report', 'inventory_warehouse_stock_report', 'inventory_warehouse_transfer_report',
            'list_attribute', 'list_batch', 'list_brand', 'list_damage_report', 'list_delivery_challan',
            'list_grn', 'list_opening_stock_entry', 'list_product', 'list_product_category',
            'list_product_variant', 'list_serial_number', 'list_stock_adjustment', 'list_stock_transfer',
            'list_unit', 'list_unit_conversion', 'list_warehouse', 'receive_stock_transfer', 'show_attribute',
            'show_batch', 'show_brand', 'show_damage_report', 'show_delivery_challan', 'show_grn',
            'show_opening_stock_entry', 'show_product', 'show_product_category', 'show_stock_adjustment',
            'show_stock_transfer', 'show_unit', 'show_unit_conversion', 'show_warehouse', 'view_serial_number',
            'write_off_batch',
        ],
        'route_files' => ['api_inventory.php'],
        'frontend_key' => 'inventory',
        'data_transfer_entities' => ['product', 'warehouse', 'stock', 'opening_stock'],
        'scheduled_commands' => [
            'batch:expire',
            'inventory:gl-reconcile',
            'inventory:valuation-snapshot',
            'products:prune-orphan-variants',
        ],
        'sort_order' => 20,
    ],

    'sales' => [
        'name' => 'Sales',
        'group' => 'foundation',
        'description' => 'Quotations, sales orders, invoices, receipts and credit notes.',
        'icon' => 'ti ti-shopping-cart',
        'requires' => ['accounting', 'inventory'],
        'permissions' => [
            'apply_customer_advance', 'approve_credit_note', 'approve_customer_advance', 'approve_invoice',
            'approve_quotation', 'approve_receipt', 'approve_sales_order', 'category_wise_sales_report',
            'create_credit_note', 'create_customer_advance', 'create_invoice', 'create_quotation',
            'create_receipt', 'create_sales_order', 'customer_wise_sales_report', 'daily_sales_report',
            'delete_credit_note', 'delete_invoice', 'delete_quotation', 'delete_receipt', 'delete_sales_order',
            'discount_report', 'edit_credit_note', 'edit_invoice', 'edit_quotation', 'edit_receipt',
            'edit_sales_order', 'list_credit_note', 'list_customer_advance', 'list_due_invoices', 'list_invoice',
            'list_quotation', 'list_receipt', 'list_sales_order', 'monthly_sales_report',
            'outstanding_sales_report', 'product_wise_sales_report', 'sales_by_item_report', 'sales_ledger_report',
            'sales_profit_report', 'sales_report_dashboard', 'sales_return_report', 'sales_summary_report',
            'sales_tax_report', 'show_credit_note', 'show_invoice', 'show_quotation', 'show_receipt',
            'show_sales_order', 'view_customer_advance', 'void_credit_note', 'void_customer_advance',
            'void_invoice', 'void_receipt', 'write_off_invoice', 'yearly_sales_report',
        ],
        'route_files' => ['api_sales.php'],
        'frontend_key' => 'sales',
        'data_transfer_entities' => ['invoice', 'sales_order'],
        'sort_order' => 30,
    ],

    'purchase' => [
        'name' => 'Purchase',
        'group' => 'foundation',
        'description' => 'Purchase orders, bills, expenses, payments and debit notes.',
        'icon' => 'ti ti-shopping-bag',
        'requires' => ['accounting', 'inventory'],
        'permissions' => [
            'approve_bill', 'approve_debit_note', 'approve_expense', 'approve_payment', 'approve_purchase_order',
            'category_wise_purchase_report', 'create_bill', 'create_debit_note', 'create_expense',
            'create_payment', 'create_purchase_order', 'daily_purchase_report', 'delete_bill',
            'delete_debit_note', 'delete_expense', 'delete_payment', 'delete_purchase_order', 'edit_bill',
            'edit_debit_note', 'edit_expense', 'edit_payment', 'edit_purchase_order', 'grn_report', 'list_bill',
            'list_debit_note', 'list_due_bills', 'list_due_expenses', 'list_expense', 'list_payment',
            'list_purchase_order', 'monthly_purchase_report', 'outstanding_purchase_report',
            'pending_purchase_report', 'purchase_discount_report', 'purchase_ledger_report',
            'purchase_return_report', 'purchase_summary_report', 'purchase_tax_report', 'show_bill',
            'show_debit_note', 'show_expense', 'show_payment', 'show_purchase_order',
            'supplier_wise_purchase_report', 'void_bill', 'void_debit_note', 'void_payment',
            'yearly_purchase_report',
        ],
        'route_files' => ['api_purchase.php'],
        'frontend_key' => 'purchase',
        'data_transfer_entities' => ['bill', 'purchase_order'],
        'sort_order' => 40,
    ],

    /*
    |--------------------------------------------------------------------------
    | Optional
    |--------------------------------------------------------------------------
    | `route_groups` marks modules whose routes currently live inside another
    | module's route file (or routes/api_admin.php). Phase 2 wraps those blocks
    | in an inline Route::middleware('module:{key}') group rather than moving
    | them, so URLs and route names stay stable.
    */

    'crm' => [
        'name' => 'CRM',
        'group' => 'optional',
        'description' => 'Leads, contacts, follow-ups, tasks, notes and the customer timeline.',
        'icon' => 'ti ti-users-group',
        'requires' => [],
        'permissions' => [
            'assign_crm_lead', 'convert_crm_lead', 'create_crm_contact', 'create_crm_follow_up',
            'create_crm_lead', 'create_crm_note', 'create_crm_task', 'delete_crm_contact',
            'delete_crm_follow_up', 'delete_crm_lead', 'delete_crm_note', 'delete_crm_task', 'edit_crm_contact',
            'edit_crm_follow_up', 'edit_crm_lead', 'edit_crm_note', 'edit_crm_task', 'list_crm_contact',
            'list_crm_follow_up', 'list_crm_lead', 'list_crm_note', 'list_crm_task', 'show_crm_lead',
            'view_crm_report', 'view_crm_timeline',
        ],
        'route_files' => ['api_crm.php'],
        'frontend_key' => 'crm',
        'scheduled_commands' => ['crm:dispatch-reminders'],
        'sort_order' => 50,
    ],

    'hr' => [
        'name' => 'HR',
        'group' => 'optional',
        'description' => 'Employees, departments, designations, attendance, leave and holidays.',
        'icon' => 'ti ti-users',
        'requires' => [],
        'permissions' => [
            'create_attendance', 'create_department', 'create_designation', 'create_employee', 'create_holiday',
            'create_leave_application', 'create_leave_type', 'delete_attendance', 'delete_department',
            'delete_designation', 'delete_employee', 'delete_holiday', 'delete_leave_application',
            'delete_leave_type', 'edit_attendance', 'edit_department', 'edit_designation', 'edit_employee',
            'edit_holiday', 'edit_leave_application', 'edit_leave_type', 'edit_work_schedule', 'list_attendance',
            'list_department', 'list_designation', 'list_employee', 'list_holiday', 'list_leave_application',
            'list_leave_type', 'show_attendance', 'show_department', 'show_designation', 'show_employee',
            'show_holiday', 'show_leave_application', 'show_leave_type', 'show_work_schedule',
        ],
        'route_files' => ['api_hr.php'],
        'frontend_key' => 'hr',
        'sort_order' => 60,
    ],

    'payroll' => [
        'name' => 'Payroll',
        'group' => 'optional',
        'description' => 'Salary components, salary structures and payroll runs.',
        'icon' => 'ti ti-cash',
        'requires' => ['hr', 'accounting'],
        'permissions' => [
            'create_payroll', 'create_salary_component', 'create_salary_structure', 'delete_payroll',
            'delete_salary_component', 'delete_salary_structure', 'edit_payroll', 'edit_salary_component',
            'edit_salary_structure', 'list_payroll', 'list_salary_component', 'list_salary_structure',
            'show_payroll', 'show_salary_component', 'show_salary_structure',
        ],
        'route_files' => [],
        'route_groups' => ['api_hr.php' => 'payroll'],
        'frontend_key' => 'payroll',
        'sort_order' => 65,
    ],

    'pos' => [
        'name' => 'Point of Sale',
        'group' => 'optional',
        'description' => 'Till sessions, counter checkout, held orders and cash movements.',
        'icon' => 'ti ti-device-laptop',
        'requires' => ['sales', 'inventory'],
        'permissions' => [
            'list_pos', 'pos_cash_movement', 'pos_checkout', 'pos_open_till', 'pos_return',
        ],
        'route_files' => [],
        'route_groups' => ['api_admin.php' => 'pos'],
        'frontend_key' => 'pos',
        'sort_order' => 70,
    ],

    'manufacturing' => [
        'name' => 'Manufacturing',
        'group' => 'optional',
        'description' => 'Bills of material, production orders and production variance.',
        'icon' => 'ti ti-tools',
        'requires' => ['inventory'],
        'permissions' => [
            'create_bom', 'create_production_order', 'delete_bom', 'delete_production_order', 'edit_bom',
            'edit_production_order', 'inventory_production_variance_report', 'list_bom', 'list_production_order',
            'show_bom', 'show_production_order',
        ],
        'route_files' => [],
        'route_groups' => ['api_inventory.php' => 'manufacturing'],
        'frontend_key' => 'manufacturing',
        'sort_order' => 80,
    ],

    'fixed-assets' => [
        'name' => 'Fixed Assets',
        'group' => 'optional',
        'description' => 'Asset register, categories and depreciation.',
        'icon' => 'ti ti-building-factory',
        'requires' => ['accounting'],
        'permissions' => [
            'create_fixed_asset', 'create_fixed_asset_category', 'delete_fixed_asset', 'edit_fixed_asset',
            'list_fixed_asset', 'list_fixed_asset_category', 'show_fixed_asset',
        ],
        'route_files' => [],
        'route_groups' => ['api_accounting.php' => 'fixed-assets'],
        'frontend_key' => 'fixed-assets',
        'sort_order' => 90,
    ],

    'budgeting' => [
        'name' => 'Budgeting',
        'group' => 'optional',
        'description' => 'Budgets, budget lines and budget-vs-actual reporting.',
        'icon' => 'ti ti-target',
        'requires' => ['accounting'],
        'permissions' => [
            'create_budget', 'delete_budget', 'edit_budget', 'list_budget', 'show_budget',
        ],
        'route_files' => [],
        'route_groups' => ['api_accounting.php' => 'budgeting'],
        'frontend_key' => 'budgeting',
        'sort_order' => 100,
    ],

    /*
    | Bank statement import, matching rules, reconciliation and cheque management.
    | Bank *account* CRUD deliberately stays in `accounting` — payments and payment
    | modes depend on it, so it must remain available when banking is switched off.
    */
    'banking' => [
        'name' => 'Banking & Reconciliation',
        'group' => 'optional',
        'description' => 'Bank statement import, reconciliation and cheque management.',
        'icon' => 'ti ti-building-bank',
        'requires' => ['accounting'],
        'permissions' => [
            'create_bank_statement', 'create_cheque', 'edit_bank_statement', 'edit_cheque',
            'list_bank_statement', 'list_cheque', 'show_cheque',
        ],
        'route_files' => [],
        'route_groups' => ['api_accounting.php' => 'banking'],
        'frontend_key' => 'banking',
        'sort_order' => 110,
    ],

    'data-transfer' => [
        'name' => 'Data Import / Export',
        'group' => 'optional',
        'description' => 'Import wizards, export jobs, mappings and scheduled exports.',
        'icon' => 'ti ti-transfer',
        'requires' => [],
        'permissions' => [
            'export_data', 'import_opening_stock', 'import_party', 'import_product', 'import_warehouse',
            'list_data_transfer',
        ],
        'route_files' => ['api_data_transfer.php'],
        'frontend_key' => 'data-transfer',
        // `data-transfer:prune` is deliberately NOT listed. It enforces the file
        // retention window, and retention must not depend on a feature flag: a
        // company that switches the module off would otherwise keep its expired
        // uploads on disk forever. It deletes nothing but files already past
        // their expiry, so it is infrastructure hygiene rather than module work.
        'scheduled_commands' => [],
        // Only the core entity. Product / warehouse / opening stock belong to
        // Inventory, invoices to Sales, and so on — see
        // DataTransferEntityTypeEnum::module(). Having the wizard does not
        // entitle a company to move a module's data it does not run.
        'data_transfer_entities' => ['party'],
        'sort_order' => 120,
    ],

    /*
    |--------------------------------------------------------------------------
    | Industry verticals
    |--------------------------------------------------------------------------
    */

    'gym' => [
        'name' => 'Gym Management',
        'group' => 'industry',
        'description' => 'Members, membership plans, renewals and check-ins.',
        'icon' => 'ti ti-barbell',
        'always_on' => false,
        'self_service' => false,
        // Members are Parties and membership plans bill through service
        // Products, so the gym vertical rests on the ERP rather than beside it.
        'requires' => ['accounting', 'inventory', 'sales'],
        'permissions' => [
            'assign_membership', 'cancel_membership', 'create_member', 'create_membership_plan',
            'delete_member', 'delete_membership_plan', 'edit_member', 'edit_membership_plan',
            'freeze_membership', 'gym_report', 'list_member', 'list_membership', 'list_membership_plan',
            'member_check_in', 'renew_membership', 'show_member',
        ],
        'route_files' => ['api_gym.php'],
        'frontend_key' => 'gym',
        'provisioning_steps' => [\App\Provisioning\Steps\Gym\GymDefaultsStep::class],
        'activator' => \App\Modules\Gym\GymModuleActivator::class,
        'scheduled_commands' => ['gym:process-membership-expiry', 'gym:dispatch-membership-reminders'],
        'models' => [
            \App\Models\Member::class,
            \App\Models\MembershipPlan::class,
            \App\Models\Membership::class,
            \App\Models\MemberCheckIn::class,
            \App\Models\MembershipFreeze::class,
        ],
        'settings_schema' => [
            'auto_invoice_on_assignment' => true,
            'allow_multiple_active_memberships' => false,
            'lapsed_renewal_continues_term' => false,
            'notify_member_directly' => false,
        ],
        'sort_order' => 200,
    ],

    'nepal-compliance' => [
        'name' => 'Nepal Compliance',
        'group' => 'optional',
        'description' => 'IRD e-billing, VAT D3/D4 registers and TDS receivables.',
        'icon' => 'ti ti-file-certificate',
        'requires' => ['accounting', 'sales', 'purchase'],
        'permissions' => [
            'approve_tds_receivable', 'create_tds_receivable', 'edit_setting', 'list_tds_receivable',
            'settle_tds_receivable', 'view_tds_receivable',
        ],
        'route_files' => [],
        'route_groups' => ['api_admin.php' => 'nepal'],
        'frontend_key' => 'nepal-compliance',
        'sort_order' => 130,
    ],

];
