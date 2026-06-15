export default [
    {
        path: "purchase-list",
        name: "admin.purchase-list",
        meta: {
            pageTitle: "Purchase List",
        },
        component: () => import("@/views/admin/purchase/list/Index.vue"),
    },
    {
        path: "purchase-orders",
        name: "admin.purchase-order-list",
        meta: {
            pageTitle: "Purchase Orders",
        },
        component: () => import("@/views/admin/purchase/order/Index.vue"),
    },
    {
        path: "purchase-bills",
        name: "admin.bill-list",
        meta: {
            pageTitle: "Bills",
        },
        component: () => import("@/views/admin/purchase/bill/Index.vue"),
    },
    {
        path: "purchase-expenses",
        name: "admin.expense-list",
        meta: {
            pageTitle: "Expenses",
        },
        component: () => import("@/views/admin/purchase/expense/Index.vue"),
    },
    {
        path: "payments",
        name: "admin.payment-list",
        meta: {
            pageTitle: "Payments",
        },
        component: () => import("@/views/admin/purchase/payment/Index.vue"),
    },
    {
        path: "debit-notes",
        name: "admin.debit-note-list",
        meta: {
            pageTitle: "Debit Notes",
        },
        component: () => import("@/views/admin/purchase/debit-note/Index.vue"),
    },
    {
        path: "purchase-report",
        name: "admin.purchase-report",
        meta: {
            pageTitle: "Purchase Report",
        },
        component: () => import("@/views/admin/purchase/PurchaseReport.vue"),
    },
    {
        path: "purchase-by-item",
        name: "admin.purchase-by-item",
        meta: {
            pageTitle: "Purchase By Item",
        },
        component: () => import("@/views/admin/purchase/PurchaseByItem.vue"),
    },
    {
        path: "purchase-summary-report",
        name: "admin.purchase-summary-report",
        meta: { pageTitle: "Purchase Summary Report" },
        component: () => import("@/views/admin/purchase/reports/PurchaseSummaryReport.vue"),
    },
    {
        path: "daily-purchase-report",
        name: "admin.daily-purchase-report",
        meta: { pageTitle: "Daily Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/DailyPurchaseReport.vue"),
    },
    {
        path: "monthly-purchase-report",
        name: "admin.monthly-purchase-report",
        meta: { pageTitle: "Monthly Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/MonthlyPurchaseReport.vue"),
    },
    {
        path: "yearly-purchase-report",
        name: "admin.yearly-purchase-report",
        meta: { pageTitle: "Yearly Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/YearlyPurchaseReport.vue"),
    },
    {
        path: "supplier-wise-purchase-report",
        name: "admin.supplier-wise-purchase-report",
        meta: { pageTitle: "Supplier Wise Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/SupplierWisePurchaseReport.vue"),
    },
    {
        path: "category-wise-purchase-report",
        name: "admin.category-wise-purchase-report",
        meta: { pageTitle: "Category Wise Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/CategoryWisePurchaseReport.vue"),
    },
    {
        path: "purchase-return-report",
        name: "admin.purchase-return-report",
        meta: { pageTitle: "Purchase Return Report" },
        component: () => import("@/views/admin/purchase/reports/PurchaseReturnReport.vue"),
    },
    {
        path: "outstanding-purchase-report",
        name: "admin.outstanding-purchase-report",
        meta: { pageTitle: "Outstanding Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/OutstandingPurchaseReport.vue"),
    },
    {
        path: "purchase-tax-report",
        name: "admin.purchase-tax-report",
        meta: { pageTitle: "Purchase Tax Report" },
        component: () => import("@/views/admin/purchase/reports/PurchaseTaxReport.vue"),
    },
    {
        path: "purchase-ledger-report",
        name: "admin.purchase-ledger-report",
        meta: { pageTitle: "Purchase Ledger" },
        component: () => import("@/views/admin/purchase/reports/PurchaseLedgerReport.vue"),
    },
    {
        path: "grn-report",
        name: "admin.grn-report",
        meta: { pageTitle: "GRN Report" },
        component: () => import("@/views/admin/purchase/reports/GrnReport.vue"),
    },
    {
        path: "pending-purchase-report",
        name: "admin.pending-purchase-report",
        meta: { pageTitle: "Pending Purchase Report" },
        component: () => import("@/views/admin/purchase/reports/PendingPurchaseReport.vue"),
    },
    {
        path: "purchase-discount-report",
        name: "admin.purchase-discount-report",
        meta: { pageTitle: "Purchase Discount Report" },
        component: () => import("@/views/admin/purchase/reports/PurchaseDiscountReport.vue"),
    },
];
