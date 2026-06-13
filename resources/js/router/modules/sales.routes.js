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
        path: "tds-challan",
        name: "admin.tds-challan-list",
        meta: {
            pageTitle: "TDS Challans",
        },
        component: () => import("@/views/admin/sales/tds-challan/Index.vue"),
    },
    {
        path: "tds-challan/:id",
        name: "admin.tds-challan-view",
        meta: {
            pageTitle: "TDS Challan View",
        },
        component: () => import("@/views/admin/sales/tds-challan/View.vue"),
    },
    {
        path: "advance-receipt",
        name: "admin.advance-receipt-list",
        meta: {
            pageTitle: "Advance Receipts",
        },
        component: () => import("@/views/admin/sales/advance-receipt/Index.vue"),
    },
    {
        path: "sales-aging",
        name: "admin.sales-aging",
        meta: { pageTitle: "AR Aging Report" },
        component: () => import("@/views/admin/sales/AgingReport.vue"),
    },
    {
        path: "party-statement",
        name: "admin.party-statement",
        meta: { pageTitle: "Party Statement" },
        component: () => import("@/views/admin/sales/PartyStatement.vue"),
    },
    {
        path: "vat-register",
        name: "admin.vat-register",
        meta: { pageTitle: "VAT Register" },
        component: () => import("@/views/admin/sales/VatRegister.vue"),
    },
    {
        path: "tds-register",
        name: "admin.tds-register",
        meta: { pageTitle: "TDS Register" },
        component: () => import("@/views/admin/sales/TdsRegister.vue"),
    },
    {
        path: "customer-outstanding",
        name: "admin.customer-outstanding",
        meta: { pageTitle: "Customer Outstanding" },
        component: () => import("@/views/admin/sales/CustomerOutstanding.vue"),
    },
];
