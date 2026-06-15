export default [
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
        component: () => import("@/views/admin/sales/invoice/Index.vue"),
    },
    {
        path: "sales-invoice/:id",
        name: "admin.invoice-view",
        meta: {
            pageTitle: "Invoice view",
        },
        component: () => import("@/views/admin/sales/invoice/View.vue"),
    },
    {
        path: "credit-notes",
        name: "admin.credit-note-list",
        meta: {
            pageTitle: "Credit Notes",
        },
        component: () => import("@/views/admin/sales/credit-note/Index.vue"),
    },
    {
        path: "sales-receipt",
        name: "admin.receipt-list",
        meta: {
            pageTitle: "Receipt List",
        },
        component: () => import("@/views/admin/sales/receipt/Index.vue"),
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
        path: "sales-summary-report",
        name: "admin.sales-summary-report",
        meta: { pageTitle: "Sales Summary Report" },
        component: () => import("@/views/admin/sales/reports/SalesSummaryReport.vue"),
    },
    {
        path: "daily-sales-report",
        name: "admin.daily-sales-report",
        meta: { pageTitle: "Daily Sales Report" },
        component: () => import("@/views/admin/sales/reports/DailySalesReport.vue"),
    },
    {
        path: "monthly-sales-report",
        name: "admin.monthly-sales-report",
        meta: { pageTitle: "Monthly Sales Report" },
        component: () => import("@/views/admin/sales/reports/MonthlySalesReport.vue"),
    },
    {
        path: "yearly-sales-report",
        name: "admin.yearly-sales-report",
        meta: { pageTitle: "Yearly Sales Report" },
        component: () => import("@/views/admin/sales/reports/YearlySalesReport.vue"),
    },
    {
        path: "customer-wise-sales-report",
        name: "admin.customer-wise-sales-report",
        meta: { pageTitle: "Customer Wise Sales Report" },
        component: () => import("@/views/admin/sales/reports/CustomerWiseSalesReport.vue"),
    },
    {
        path: "category-wise-sales-report",
        name: "admin.category-wise-sales-report",
        meta: { pageTitle: "Category Wise Sales Report" },
        component: () => import("@/views/admin/sales/reports/CategoryWiseSalesReport.vue"),
    },
    {
        path: "sales-return-report",
        name: "admin.sales-return-report",
        meta: { pageTitle: "Sales Return Report" },
        component: () => import("@/views/admin/sales/reports/SalesReturnReport.vue"),
    },
    {
        path: "outstanding-sales-report",
        name: "admin.outstanding-sales-report",
        meta: { pageTitle: "Outstanding Sales Report" },
        component: () => import("@/views/admin/sales/reports/OutstandingSalesReport.vue"),
    },
    {
        path: "sales-tax-report",
        name: "admin.sales-tax-report",
        meta: { pageTitle: "Sales Tax Report" },
        component: () => import("@/views/admin/sales/reports/SalesTaxReport.vue"),
    },
    {
        path: "sales-profit-report",
        name: "admin.sales-profit-report",
        meta: { pageTitle: "Sales Profit Report" },
        component: () => import("@/views/admin/sales/reports/SalesProfitReport.vue"),
    },
    {
        path: "discount-report",
        name: "admin.discount-report",
        meta: { pageTitle: "Discount Report" },
        component: () => import("@/views/admin/sales/reports/DiscountReport.vue"),
    },
    {
        path: "sales-ledger-report",
        name: "admin.sales-ledger-report",
        meta: { pageTitle: "Sales Ledger Report" },
        component: () => import("@/views/admin/sales/reports/SalesLedgerReport.vue"),
    },
    {
        path: "customer-transaction-report",
        name: "admin.customer-transaction-report",
        meta: { pageTitle: "Customer Transaction Report" },
        component: () => import("@/views/admin/sales/reports/CustomerTransactionReport.vue"),
    },
    {
        path: "customer-statement",
        name: "admin.customer-statement",
        meta: { pageTitle: "Customer Statement" },
        component: () => import("@/views/admin/sales/reports/CustomerStatement.vue"),
    },
];
