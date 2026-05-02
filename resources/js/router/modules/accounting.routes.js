export default [
    {
        path: "chart-of-accounts",
        name: "admin.chart-of-accounts",
        meta: {
            pageTitle: "Chart Of Accounts",
        },
        component: () => import("@/views/admin/accounting/coa/Index.vue"),
    },
    {
        path: "journal-voucher",
        name: "admin.journal-voucher",
        meta: {
            pageTitle: "Journal Voucher",
        },
        component: () => import("@/views/admin/accounting/journal-voucher/Index.vue"),
    },
    {
        path: "payment-voucher",
        name: "admin.payment-voucher",
        meta: {
            pageTitle: "Payment Voucher",
        },
        component: () => import("@/views/admin/accounting/payment-voucher/Index.vue"),
    },
    {
        path: "receipt-voucher",
        name: "admin.receipt-voucher",
        meta: {
            pageTitle: "Receipt Voucher",
        },
        component: () => import("@/views/admin/accounting/receipt-voucher/Index.vue"),
    },
    {
        path: "trial-balance",
        name: "admin.trial-balance",
        meta: {
            pageTitle: "Trial Balance",
        },
        component: () => import("@/views/admin/accounting/reports/TrialBalance.vue"),
    },
    {
        path: "balance-sheet",
        name: "admin.balance-sheet",
        meta: {
            pageTitle: "Balance Sheet",
        },
        component: () => import("@/views/admin/accounting/reports/BalanceSheet.vue"),
    },
    {
        path: "profit-and-loss",
        name: "admin.profit-and-loss",
        meta: {
            pageTitle: "Profit and Loss",
        },
        component: () => import("@/views/admin/accounting/reports/ProfitLoss.vue"),
    },
    {
        path: "journal-report",
        name: "admin.journal-report",
        meta: {
            pageTitle: "Journal Report",
        },
        component: () => import("@/views/admin/accounting/reports/JournalReport.vue"),
    },
    {
        path: "general-ledger",
        name: "admin.general-ledger",
        meta: {
            pageTitle: "General Ledger",
        },
        component: () => import("@/views/admin/accounting/reports/GeneralLedger.vue"),
    },
    {
        path: "cash-flow",
        name: "admin.cash-flow",
        meta: {pageTitle: "Cash Flow Statement"},
        component: () => import("@/views/admin/accounting/reports/CashFlow.vue"),
    },
    {
        path: "vat-return",
        name: "admin.vat-return",
        meta: {pageTitle: "VAT Return (D3)"},
        component: () => import("@/views/admin/accounting/reports/VatReturn.vue"),
    },
    {
        path: "vat-sales-register",
        name: "admin.vat-sales-register",
        meta: {pageTitle: "Bikri Khata (VAT Sales Register)"},
        component: () => import("@/views/admin/accounting/reports/VatSalesRegister.vue"),
    },
    {
        path: "vat-purchase-register",
        name: "admin.vat-purchase-register",
        meta: {pageTitle: "Kharid Khata (VAT Purchase Register)"},
        component: () => import("@/views/admin/accounting/reports/VatPurchaseRegister.vue"),
    },
    {
        path: "tds-report",
        name: "admin.tds-report",
        meta: {pageTitle: "TDS Report"},
        component: () => import("@/views/admin/accounting/reports/TdsReport.vue"),
    },
    {
        path: "tds-challan",
        name: "admin.tds-challan",
        meta: {pageTitle: "TDS Challan & Certificate"},
        component: () => import("@/views/admin/accounting/tds-challan/Index.vue"),
    },
    {
        path: "ird-sync",
        name: "admin.ird-sync",
        meta: {pageTitle: "IRD EBS Sync Status"},
        component: () => import("@/views/admin/accounting/ird-sync/Index.vue"),
    },
    {
        path: "ar-aging",
        name: "admin.ar-aging",
        meta: {pageTitle: "AR Aging Report"},
        component: () => import("@/views/admin/accounting/reports/ArAging.vue"),
    },
    {
        path: "ap-aging",
        name: "admin.ap-aging",
        meta: {pageTitle: "AP Aging Report"},
        component: () => import("@/views/admin/accounting/reports/ApAging.vue"),
    },
    {
        path: "opening-balances",
        name: "admin.opening-balances",
        meta: {pageTitle: "Opening Balances"},
        component: () => import("@/views/admin/accounting/opening-balances/Index.vue"),
    },
    {
        path: "accounting-periods",
        name: "admin.accounting-periods",
        meta: {pageTitle: "Accounting Periods"},
        component: () => import("@/views/admin/accounting/accounting-periods/Index.vue"),
    },
    {
        path: "bank-reconciliation",
        name: "admin.bank-reconciliation",
        meta: {pageTitle: "Bank Reconciliation"},
        component: () => import("@/views/admin/accounting/bank-reconciliation/Index.vue"),
    },
    {
        path: "recurring-journals",
        name: "admin.recurring-journal-list",
        meta: {pageTitle: "Recurring Journals"},
        component: () => import("@/views/admin/accounting/recurring-journal/Index.vue"),
    },
    {
        path: "fixed-assets",
        name: "admin.fixed-asset-list",
        meta: {pageTitle: "Fixed Assets"},
        component: () => import("@/views/admin/accounting/fixed-assets/Index.vue"),
    },
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
    {
        path: "accounting/branch-pl",
        name: "admin.branch-pl",
        meta: {pageTitle: "Branch P&L / Consolidated"},
        component: () => import("@/views/admin/accounting/branch-pl/Index.vue"),
    },
]
