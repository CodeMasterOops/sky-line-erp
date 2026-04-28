import AdminLayout from "@/layouts/admin/AppLayout.vue";

const routes = [
    {
        path: "/",
        redirect: "/admin/dashboard",
    },
    {
        path: "/admin/login",
        name: "admin.login",
        meta: {
            isGuest: true,
            isAdmin: true,
            pageTitle: "Login",
        },
        component: () => import("@/views/admin/auth/Login.vue"),
    },
    {
        path: "/admin/register",
        name: "admin.register",
        meta: {
            isGuest: true,
            isAdmin: true,
            pageTitle: "Register",
        },
        component: () => import("@/views/admin/auth/Register.vue"),
    },
    {
        path: "/admin/forgot-password",
        name: "admin.forgot-password",
        meta: {
            isGuest: true,
            isAdmin: true,
            pageTitle: "Forgot Password",
        },
        component: () => import("@/views/admin/auth/ForgotPassword.vue"),
    },
    {
        path: "/admin",
        component: AdminLayout,
        redirect: "/admin/dashboard",
        meta: {requiresAuth: true, isAdmin: true},
        children: [
            {
                path: "dashboard",
                name: "admin.dashboard",
                meta: {
                    pageTitle: "Dashboard",
                },
                component: () => import("@/views/admin/Dashboard.vue"),
            },
            {
                path: "profile",
                redirect: "/admin/settings/profile",
            },
            {
                path: "billing/pricing",
                name: "admin.billing-pricing",
                meta: {
                    pageTitle: "Plans & pricing",
                },
                component: () => import("@/views/admin/billing/Pricing.vue"),
            },
            {
                path: "/admin/settings",
                component: () =>
                    import("@/views/admin/settings/general-settings/settings-index.vue"), // a wrapper layout for settings
                children: [
                    {
                        path: "setting",
                        name: "admin.setting",
                        component: () =>
                            import("@/views/admin/settings/general-settings/Setting.vue"),
                        meta: {pageTitle: "Company Setting"},
                    },
                    {
                        path: "general-settings",
                        name: "admin.general-settings",
                        component: () =>
                            import("@/views/admin/settings/general-settings/general-settings.vue"),
                        meta: {pageTitle: "General Settings"},
                    },
                    {
                        path: "security-settings",
                        name: "admin.security-settings",
                        component: () =>
                            import("@/views/admin/settings/general-settings/security-settings.vue"),
                        meta: {pageTitle: "Security Settings"},
                    },
                    {
                        path: "notifications",
                        name: "admin.notifications",
                        component: () =>
                            import("@/views/admin/settings/general-settings/notifications.vue"),
                        meta: {pageTitle: "Notifications"},
                    },
                    {
                        path: "profile",
                        name: "admin.profile",
                        meta: { pageTitle: "Profile" },
                        component: () => import("@/views/admin/profile/Index.vue"),
                    },
                    {
                        path: 'tax',
                        name: 'admin.tax-list',
                        meta: {
                            pageTitle: 'Tax List'
                        },
                        component: () => import('@/views/admin/settings/tax/Index.vue')
                    },
                    {
                        path: 'payment-mode',
                        name: 'admin.payment-mode-list',
                        meta: {
                            pageTitle: 'Payment Modes'
                        },
                        component: () => import('@/views/admin/settings/payment-mode/Index.vue')
                    },
                    {
                        path: 'ird-settings',
                        name: 'admin.ird-settings',
                        meta: { pageTitle: 'IRD EBS Settings' },
                        component: () => import('@/views/admin/settings/ird/IrdSettings.vue'),
                    },
                    {
                        path: 'branches',
                        name: 'admin.branch-list',
                        meta: { pageTitle: 'Branch Management' },
                        component: () => import('@/views/admin/settings/branches/Index.vue'),
                    },
                    {
                        path: 'account-settings',
                        name: 'admin.account-settings',
                        meta: { pageTitle: 'Account Settings' },
                        component: () => import('@/views/admin/accounting/account-setting/Index.vue'),
                    },
                    {
                        path: "", // default child route
                        redirect: "setting", // redirect /admin/settings -> /admin/settings/setting (company settings)
                    },
                ],
            },

            {
                path: "user-management",
                redirect: "/admin/user-management/user",
                children: [
                    {
                        path: "role",
                        name: "admin.role-list",
                        meta: {
                            pageTitle: "Role List",
                        },
                        component: () =>
                            import("@/views/admin/user-management/role/Index.vue"),
                    },
                    {
                        path: "role/create",
                        name: "admin.role-create",
                        meta: {
                            pageTitle: "Add Role",
                        },
                        component: () =>
                            import("@/views/admin/user-management/role/Create.vue"),
                    },
                    {
                        path: "role/:id/edit",
                        name: "admin.role-edit",
                        meta: {
                            pageTitle: "Edit Role",
                        },
                        component: () =>
                            import("@/views/admin/user-management/role/Edit.vue"),
                    },
                    {
                        path: "user",
                        name: "admin.user-list",
                        meta: {
                            pageTitle: "User List",
                        },
                        component: () =>
                            import("@/views/admin/user-management/user/Index.vue"),
                    },
                ],
            },
            {
                path: "notification",
                name: "admin.notification-list",
                meta: {
                    pageTitle: "Notifications",
                },
                component: () => import("@/views/admin/notification/Index.vue"),
            },
            {
                path: "sales-list",
                name: "admin.sales-list",
                meta: {
                    pageTitle: "Sales Order",
                },
                component: () => import("@/views/admin/sales/sales-order/Index.vue"),
            },
            {
                path: "sales-invoice",
                name: "admin.invoice-list",
                meta: {
                    pageTitle: "Invoice",
                },
                component: () =>
                    import("@/views/admin/sales/invoice/Index.vue"),
            },
            {
                path: "sales-invoice/:id",
                name: "admin.invoice-view",
                meta: {
                    pageTitle: "Invoice view",
                },
                component: () =>
                    import("@/views/admin/sales/invoice/View.vue"),
            },
            {
                path: "credit-notes",
                name: "admin.credit-note-list",
                meta: {
                    pageTitle: "Credit Notes",
                },
                component: () =>
                    import("@/views/admin/sales/credit-note/Index.vue"),
            },
            {
                path: "sales-receipt",
                name: "admin.receipt-list",
                meta: {
                    pageTitle: "Receipt List",
                },
                component: () =>
                    import("@/views/admin/sales/receipt/Index.vue"),
            },
            {
                path: "sales-returns",
                name: "admin.sales-returns",
                meta: {
                    pageTitle: "Sales Returns",
                },
                component: () => import("@/views/admin/sales/returns/Index.vue"),
            },
            {
                path: "quotation-list",
                name: "admin.quotation-list",
                meta: {
                    pageTitle: "Quotation List",
                },
                component: () => import("@/views/admin/sales/quotation/Index.vue"),
            },
            {
                path: "reports",
                name: "admin.reports-hub",
                meta: {
                    pageTitle: "Reports",
                },
                component: () => import("@/views/admin/reports/ReportsHub.vue"),
            },
            {
                path: "sales-report",
                name: "admin.sales-report",
                meta: {
                    pageTitle: "Sales Report",
                },
                component: () => import("@/views/admin/sales/SalesReport.vue"),
            },
            {
                path: "sales-by-item",
                name: "admin.sales-by-item",
                meta: {
                    pageTitle: "Sales By Item",
                },
                component: () => import("@/views/admin/sales/SalesByItem.vue"),
            },
            {
                path: "brand",
                name: "admin.brand-list",
                meta: {
                    pageTitle: "Brand List",
                },
                component: () =>
                    import("@/views/admin/inventory/brand/Index.vue"),
            },
            {
                path: "unit",
                name: "admin.unit-list",
                meta: {
                    pageTitle: "Unit List",
                },
                component: () =>
                    import("@/views/admin/inventory/unit/Index.vue"),
            },
            {
                path: "warehouse",
                name: "admin.warehouse-list",
                meta: {
                    pageTitle: "Warehouse List",
                },
                component: () =>
                    import("@/views/admin/inventory/warehouse/Index.vue"),
            },
            {
                path: "stock-transfer",
                name: "admin.stock-transfer",
                meta: {
                    pageTitle: "Stock Transfer",
                },
                component: () =>
                    import("@/views/admin/inventory/stock-transfer/Index.vue"),
            },
            {
                path: "stock-adjustment",
                name: "admin.stock-adjustment",
                meta: {
                    pageTitle: "Stock Adjustment",
                },
                component: () =>
                    import("@/views/admin/inventory/stock-adjustment/Index.vue"),
            },
            {
                path: "stock-reconciliation",
                name: "admin.stock-reconciliation",
                meta: {
                    pageTitle: "Stock Reconciliation",
                },
                component: () =>
                    import("@/views/admin/inventory/stock-reconciliation/Index.vue"),
            },
            {
                path: "product-category",
                name: "admin.product-category-list",
                meta: {
                    pageTitle: "Product Category",
                },
                component: () =>
                    import("@/views/admin/inventory/product-category/Index.vue"),
            },
            {
                path: "product/create",
                name: "admin.product-create",
                meta: {
                    pageTitle: "Add Product",
                },
                component: () =>
                    import("@/views/admin/inventory/product/Create.vue"),
            },
            {
                path: "product/:id/edit",
                name: "admin.product-edit",
                meta: {
                    pageTitle: "Edit Product",
                },
                component: () =>
                    import("@/views/admin/inventory/product/Edit.vue"),
            },
            {
                path: "product",
                name: "admin.product-list",
                meta: {
                    pageTitle: "Product List",
                },
                component: () =>
                    import("@/views/admin/inventory/product/ProductList.vue"),
            },
            {
                path: "party",
                name: "admin.party-list",
                meta: {
                    pageTitle: "Party List",
                },
                component: () => import("@/views/admin/party/Index.vue"),
            },
            {
                path: "variant-attributes",
                name: "admin.variant-attributes",
                meta: {
                    pageTitle: "Variant Attributes",
                },
                component: () =>
                    import("@/views/admin/inventory/attribute/Index.vue"),
            },
            {
                path: "barcode",
                name: "admin.barcode",
                meta: {
                    pageTitle: "Print Barcode",
                },
                component: () =>
                    import("@/views/admin/inventory/barcode/Index.vue"),
            },
            {
                path: "purchase-list",
                name: "admin.purchase-list",
                meta: {
                    pageTitle: "Purchase List",
                },
                component: () =>
                    import("@/views/admin/purchase/list/Index.vue"),
            },
            {
                path: "purchase-orders",
                name: "admin.purchase-order-list",
                meta: {
                    pageTitle: "Purchase Orders",
                },
                component: () =>
                    import("@/views/admin/purchase/order/Index.vue"),
            },
            {
                path: "purchase-bills",
                name: "admin.bill-list",
                meta: {
                    pageTitle: "Bills",
                },
                component: () =>
                    import("@/views/admin/purchase/bill/Index.vue"),
            },
            {
                path: "purchase-expenses",
                name: "admin.expense-list",
                meta: {
                    pageTitle: "Expenses",
                },
                component: () =>
                    import("@/views/admin/purchase/expense/Index.vue"),
            },
            {
                path: "payments",
                name: "admin.payment-list",
                meta: {
                    pageTitle: "Payments",
                },
                component: () =>
                    import("@/views/admin/purchase/payment/Index.vue"),
            },
            {
                path: "debit-notes",
                name: "admin.debit-note-list",
                meta: {
                    pageTitle: "Debit Notes",
                },
                component: () =>
                    import("@/views/admin/purchase/debit-note/Index.vue"),
            },
            {
                path: "purchase-report",
                name: "admin.purchase-report",
                meta: {
                    pageTitle: "Purchase Report",
                },
                component: () =>
                    import("@/views/admin/purchase/PurchaseReport.vue"),
            },
            {
                path: "purchase-by-item",
                name: "admin.purchase-by-item",
                meta: {
                    pageTitle: "Purchase By Item",
                },
                component: () =>
                    import("@/views/admin/purchase/PurchaseByItem.vue"),
            },
            {
                path: "chart-of-accounts",
                name: "admin.chart-of-accounts",
                meta: {
                    pageTitle: "Chart Of Accounts",
                },
                component: () =>
                    import("@/views/admin/accounting/coa/Index.vue"),
            },
            {
                path: "journal-voucher",
                name: "admin.journal-voucher",
                meta: {
                    pageTitle: "Journal Voucher",
                },
                component: () =>
                    import("@/views/admin/accounting/journal-voucher/Index.vue"),
            },
            {
                path: "payment-voucher",
                name: "admin.payment-voucher",
                meta: {
                    pageTitle: "Payment Voucher",
                },
                component: () =>
                    import("@/views/admin/accounting/payment-voucher/Index.vue"),
            },
            {
                path: "receipt-voucher",
                name: "admin.receipt-voucher",
                meta: {
                    pageTitle: "Receipt Voucher",
                },
                component: () =>
                    import("@/views/admin/accounting/receipt-voucher/Index.vue"),
            },
            {
                path: "trial-balance",
                name: "admin.trial-balance",
                meta: {
                    pageTitle: "Trial Balance",
                },
                component: () =>
                    import("@/views/admin/accounting/reports/TrialBalance.vue"),
            },
            {
                path: "balance-sheet",
                name: "admin.balance-sheet",
                meta: {
                    pageTitle: "Balance Sheet",
                },
                component: () =>
                    import("@/views/admin/accounting/reports/BalanceSheet.vue"),
            },
            {
                path: "profit-and-loss",
                name: "admin.profit-and-loss",
                meta: {
                    pageTitle: "Profit and Loss",
                },
                component: () =>
                    import("@/views/admin/accounting/reports/ProfitLoss.vue"),
            },
            {
                path: "journal-report",
                name: "admin.journal-report",
                meta: {
                    pageTitle: "Journal Report",
                },
                component: () =>
                    import("@/views/admin/accounting/reports/JournalReport.vue"),
            },
            {
                path: "general-ledger",
                name: "admin.general-ledger",
                meta: {
                    pageTitle: "General Ledger",
                },
                component: () =>
                    import("@/views/admin/accounting/reports/GeneralLedger.vue"),
            },
            {
                path: "cash-flow",
                name: "admin.cash-flow",
                meta: { pageTitle: "Cash Flow Statement" },
                component: () => import("@/views/admin/accounting/reports/CashFlow.vue"),
            },
            {
                path: "vat-return",
                name: "admin.vat-return",
                meta: { pageTitle: "VAT Return (D3)" },
                component: () => import("@/views/admin/accounting/reports/VatReturn.vue"),
            },
            {
                path: "vat-sales-register",
                name: "admin.vat-sales-register",
                meta: { pageTitle: "Bikri Khata (VAT Sales Register)" },
                component: () => import("@/views/admin/accounting/reports/VatSalesRegister.vue"),
            },
            {
                path: "vat-purchase-register",
                name: "admin.vat-purchase-register",
                meta: { pageTitle: "Kharid Khata (VAT Purchase Register)" },
                component: () => import("@/views/admin/accounting/reports/VatPurchaseRegister.vue"),
            },
            {
                path: "tds-report",
                name: "admin.tds-report",
                meta: { pageTitle: "TDS Report" },
                component: () => import("@/views/admin/accounting/reports/TdsReport.vue"),
            },
            {
                path: "tds-challan",
                name: "admin.tds-challan",
                meta: { pageTitle: "TDS Challan & Certificate" },
                component: () => import("@/views/admin/accounting/tds-challan/Index.vue"),
            },
            {
                path: "ird-sync",
                name: "admin.ird-sync",
                meta: { pageTitle: "IRD EBS Sync Status" },
                component: () => import("@/views/admin/accounting/ird-sync/Index.vue"),
            },
            {
                path: "ar-aging",
                name: "admin.ar-aging",
                meta: { pageTitle: "AR Aging Report" },
                component: () => import("@/views/admin/accounting/reports/ArAging.vue"),
            },
            {
                path: "ap-aging",
                name: "admin.ap-aging",
                meta: { pageTitle: "AP Aging Report" },
                component: () => import("@/views/admin/accounting/reports/ApAging.vue"),
            },
            {
                path: "opening-balances",
                name: "admin.opening-balances",
                meta: { pageTitle: "Opening Balances" },
                component: () => import("@/views/admin/accounting/opening-balances/Index.vue"),
            },
            {
                path: "accounting-periods",
                name: "admin.accounting-periods",
                meta: { pageTitle: "Accounting Periods" },
                component: () => import("@/views/admin/accounting/accounting-periods/Index.vue"),
            },
            {
                path: "bank-reconciliation",
                name: "admin.bank-reconciliation",
                meta: { pageTitle: "Bank Reconciliation" },
                component: () => import("@/views/admin/accounting/bank-reconciliation/Index.vue"),
            },
            {
                path: "recurring-journals",
                name: "admin.recurring-journal-list",
                meta: { pageTitle: "Recurring Journals" },
                component: () => import("@/views/admin/accounting/recurring-journal/Index.vue"),
            },
            {
                path: "fixed-assets",
                name: "admin.fixed-asset-list",
                meta: { pageTitle: "Fixed Assets" },
                component: () => import("@/views/admin/accounting/fixed-assets/Index.vue"),
            },
            {
                path: "grn",
                name: "admin.grn-list",
                meta: { pageTitle: "Goods Received Notes" },
                component: () => import("@/views/admin/inventory/grn/Index.vue"),
            },
            {
                path: "grn/create",
                name: "admin.grn-create",
                meta: { pageTitle: "Create GRN" },
                component: () => import("@/views/admin/inventory/grn/Create.vue"),
            },
            {
                path: "grn/:id",
                name: "admin.grn-view",
                meta: { pageTitle: "GRN Detail" },
                component: () => import("@/views/admin/inventory/grn/View.vue"),
            },
            {
                path: "delivery-challans",
                name: "admin.delivery-challan-list",
                meta: { pageTitle: "Delivery Challans" },
                component: () => import("@/views/admin/inventory/delivery-challan/Index.vue"),
            },
            {
                path: "delivery-challans/create",
                name: "admin.delivery-challan-create",
                meta: { pageTitle: "Create Delivery Challan" },
                component: () => import("@/views/admin/inventory/delivery-challan/Create.vue"),
            },
            {
                path: "delivery-challans/:id",
                name: "admin.delivery-challan-view",
                meta: { pageTitle: "Delivery Challan Detail" },
                component: () => import("@/views/admin/inventory/delivery-challan/View.vue"),
            },
            {
                path: "inventory-valuation",
                name: "admin.inventory-valuation",
                meta: { pageTitle: "Inventory Valuation" },
                component: () => import("@/views/admin/inventory/reports/InventoryValuation.vue"),
            },
            {
                path: "stock-aging",
                name: "admin.stock-aging",
                meta: { pageTitle: "Stock Aging" },
                component: () => import("@/views/admin/inventory/reports/StockAging.vue"),
            },
            {
                path: "reorder-alerts",
                name: "admin.reorder-alerts",
                meta: { pageTitle: "Reorder Alerts" },
                component: () => import("@/views/admin/inventory/reports/ReorderAlerts.vue"),
            },
            {
                path: "serial-numbers",
                name: "admin.serial-numbers",
                meta: { pageTitle: "Serial / Lot Numbers" },
                component: () => import("@/views/admin/inventory/serial-numbers/Index.vue"),
            },

            // HR & Payroll Module
            {
                path: "hr/employees",
                name: "admin.hr-employee-list",
                meta: {pageTitle: "Employees"},
                component: () => import("@/views/admin/hr/employees/Index.vue"),
            },
            {
                path: "hr/employees/create",
                name: "admin.hr-employee-create",
                meta: {pageTitle: "Add Employee"},
                component: () => import("@/views/admin/hr/employees/Form.vue"),
            },
            {
                path: "hr/employees/:id/edit",
                name: "admin.hr-employee-edit",
                meta: {pageTitle: "Edit Employee"},
                component: () => import("@/views/admin/hr/employees/Form.vue"),
            },
            {
                path: "hr/departments",
                name: "admin.hr-department-list",
                meta: {pageTitle: "Departments"},
                component: () => import("@/views/admin/hr/departments/Index.vue"),
            },
            {
                path: "hr/designations",
                name: "admin.hr-designation-list",
                meta: {pageTitle: "Designations"},
                component: () => import("@/views/admin/hr/designations/Index.vue"),
            },
            {
                path: "hr/attendance",
                name: "admin.hr-attendance",
                meta: {pageTitle: "Attendance"},
                component: () => import("@/views/admin/hr/attendance/Index.vue"),
            },
            {
                path: "hr/leave-applications",
                name: "admin.hr-leave-applications",
                meta: {pageTitle: "Leave Applications"},
                component: () => import("@/views/admin/hr/leave/Applications.vue"),
            },
            {
                path: "hr/leave-types",
                name: "admin.hr-leave-types",
                meta: {pageTitle: "Leave Types"},
                component: () => import("@/views/admin/hr/leave/Types.vue"),
            },
            {
                path: "hr/holidays",
                name: "admin.hr-holidays",
                meta: {pageTitle: "Holidays"},
                component: () => import("@/views/admin/hr/holidays/Index.vue"),
            },
            {
                path: "hr/salary-components",
                name: "admin.hr-salary-components",
                meta: {pageTitle: "Salary Components"},
                component: () => import("@/views/admin/hr/salary/Components.vue"),
            },
            {
                path: "hr/salary-structure",
                name: "admin.hr-salary-structure",
                meta: {pageTitle: "Salary Structure"},
                component: () => import("@/views/admin/hr/salary/Structure.vue"),
            },
            {
                path: "hr/payroll",
                name: "admin.hr-payroll",
                meta: {pageTitle: "Payroll Runs"},
                component: () => import("@/views/admin/hr/payroll/Run.vue"),
            },
            {
                path: "hr/payroll/:id",
                name: "admin.hr-payroll-detail",
                meta: {pageTitle: "Payroll Detail"},
                component: () => import("@/views/admin/hr/payroll/Detail.vue"),
            },
            {
                path: "hr/payslip/:id",
                name: "admin.hr-payslip",
                meta: {pageTitle: "Payslip"},
                component: () => import("@/views/admin/hr/payroll/Payslip.vue"),
            },
            {
                path: "hr/reports/payroll-summary",
                name: "admin.hr-report-payroll",
                meta: {pageTitle: "Payroll Summary Report"},
                component: () => import("@/views/admin/hr/reports/PayrollSummary.vue"),
            },
            {
                path: "hr/reports/attendance-summary",
                name: "admin.hr-report-attendance",
                meta: {pageTitle: "Attendance Summary Report"},
                component: () => import("@/views/admin/hr/reports/AttendanceSummary.vue"),
            },
            {
                path: "hr/reports/leave-balance",
                name: "admin.hr-report-leave",
                meta: {pageTitle: "Leave Balance Report"},
                component: () => import("@/views/admin/hr/reports/LeaveBalance.vue"),
            },
            {
                path: "hr/reports/tds-salary",
                name: "admin.hr-report-tds-salary",
                meta: {pageTitle: "TDS Salary Report"},
                component: () => import("@/views/admin/hr/reports/TdsSalaryReport.vue"),
            },
            // ---- Phase 3: Inventory Enhancements ----
            {
                path: "inventory/batches",
                name: "admin.batch-list",
                meta: {pageTitle: "Batch / Lot Tracking"},
                component: () => import("@/views/admin/inventory/batch/Index.vue"),
            },
            {
                path: "inventory/bom",
                name: "admin.bom-list",
                meta: {pageTitle: "Bill of Materials"},
                component: () => import("@/views/admin/inventory/bom/Index.vue"),
            },
            {
                path: "inventory/production-orders",
                name: "admin.production-order-list",
                meta: {pageTitle: "Production Orders"},
                component: () => import("@/views/admin/inventory/production/Index.vue"),
            },
            // ---- Phase 5: Finance & Banking ----
            {
                path: "banking/cheques",
                name: "admin.cheque-list",
                meta: {pageTitle: "PDC Cheque Management"},
                component: () => import("@/views/admin/banking/cheques/Index.vue"),
            },
            {
                path: "accounting/budget",
                name: "admin.budget-list",
                meta: {pageTitle: "Budget Management"},
                component: () => import("@/views/admin/accounting/budget/Index.vue"),
            },
            {
                path: "accounting/cash-flow-forecast",
                name: "admin.cash-flow-forecast",
                meta: {pageTitle: "Cash Flow Forecast"},
                component: () => import("@/views/admin/accounting/cash-flow-forecast/Index.vue"),
            },
            // ---- Phase 6: Multi-branch ----
            {
                path: "accounting/branch-pl",
                name: "admin.branch-pl",
                meta: {pageTitle: "Branch P&L / Consolidated"},
                component: () => import("@/views/admin/accounting/branch-pl/Index.vue"),
            },
        ],
    },
    {
        path: "/pos",
        component: () => import("@/layouts/PosLayout.vue"),
        meta: {
            requiresAuth: true,
            isAdmin: true,
            pageTitle: "POS",
        },
        children: [
            {
                path: "",
                name: "admin.pos",
                meta: {pageTitle: "POS"},
                component: () => import("@/views/admin/pos/pos-two.vue"),
            },
        ],
    },
];

export default routes;
