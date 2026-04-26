/**
 * Permission requirement per admin route name. Aligned with @Permissions in Api/Admin controllers.
 * - null: any authenticated admin user
 * - string: single permission (must pass hasPermission)
 * - { any: string[] }: user needs at least one of these (e.g. view vs list)
 */
export const ADMIN_ROUTE_PERMISSIONS = {
    'admin.dashboard': null,
    'admin.profile': null,
    'admin.setting': 'list_setting',
    'admin.general-settings': 'list_setting',
    'admin.security-settings': 'list_setting',
    'admin.notifications': 'list_setting',
    'admin.tax-list': 'list_tax',
    'admin.payment-mode-list': 'list_payment_mode',
    'admin.branch-list': 'list_branch',
    'admin.role-list': 'list_role',
    'admin.role-create': 'create_role',
    'admin.role-edit': 'edit_role',
    'admin.user-list': 'list_user',
    'admin.notification-list': null,
    'admin.sales-list': 'list_sales_order',
    'admin.invoice-list': 'list_invoice',
    'admin.invoice-view': { any: ['list_invoice', 'show_invoice'] },
    'admin.credit-note-list': 'list_credit_note',
    'admin.receipt-list': 'list_receipt',
    'admin.sales-returns': 'list_sales_order',
    'admin.quotation-list': 'list_quotation',
    'admin.sales-report': 'list_sales_order',
    'admin.brand-list': 'list_brand',
    'admin.unit-list': 'list_unit',
    'admin.warehouse-list': 'list_warehouse',
    'admin.stock-transfer': 'list_stock_transfer',
    'admin.stock-adjustment': 'list_stock_adjustment',
    'admin.product-category-list': 'list_product_category',
    'admin.product-create': 'create_product',
    'admin.product-edit': 'edit_product',
    'admin.product-list': 'list_product',
    'admin.party-list': 'list_party',
    'admin.variant-attributes': 'list_attribute',
    'admin.barcode': 'list_product',
    'admin.purchase-list': 'list_purchase_order',
    'admin.purchase-order-list': 'list_purchase_order',
    'admin.bill-list': 'list_bill',
    'admin.expense-list': 'list_expense',
    'admin.payment-list': 'list_payment',
    'admin.debit-note-list': 'list_debit_note',
    'admin.purchase-report': 'list_bill',
    'admin.chart-of-accounts': 'list_account',
    'admin.account-settings': 'list_account_setting',
    'admin.journal-voucher': 'list_journal_voucher',
    'admin.payment-voucher': 'list_payment_voucher',
    'admin.receipt-voucher': 'list_receipt_voucher',
    'admin.trial-balance': 'list_account',
    'admin.balance-sheet': 'list_account',
    'admin.profit-and-loss': 'list_account',
    'admin.journal-report': 'list_account',
    'admin.general-ledger': 'list_account',
    'admin.hr-employee-list': 'list_employee',
    'admin.hr-employee-create': 'create_employee',
    'admin.hr-employee-edit': 'edit_employee',
    'admin.hr-department-list': 'list_department',
    'admin.hr-designation-list': 'list_designation',
    'admin.hr-attendance': 'list_attendance',
    'admin.hr-leave-applications': 'list_leave_application',
    'admin.hr-leave-types': 'list_leave_type',
    'admin.hr-holidays': 'list_holiday',
    'admin.hr-salary-components': 'list_salary_component',
    'admin.hr-salary-structure': 'list_salary_structure',
    'admin.hr-payroll': 'list_payroll',
    'admin.hr-payroll-detail': { any: ['list_payroll', 'show_payroll'] },
    'admin.hr-payslip': { any: ['list_payroll', 'show_payroll'] },
    'admin.hr-report-payroll': 'list_payroll',
    'admin.hr-report-attendance': 'list_attendance',
    'admin.hr-report-leave': 'list_leave_application',
    'admin.pos': 'list_product',
};

/**
 * @param {string|undefined} routeName
 * @returns {null|string|{any: string[]}|undefined} undefined = not in map (do not enforce)
 */
export function getAdminRoutePermission(routeName) {
    if (!routeName) return undefined;
    return Object.prototype.hasOwnProperty.call(
        ADMIN_ROUTE_PERMISSIONS,
        routeName
    )
        ? ADMIN_ROUTE_PERMISSIONS[routeName]
        : undefined;
}
