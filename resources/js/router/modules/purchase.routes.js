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
];
