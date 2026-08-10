export default [
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
        meta: {pageTitle: "Profile"},
        component: () => import("@/views/admin/profile/Index.vue"),
    },
    {
        path: "data-transfer",
        name: "admin.data-transfer-list",
        meta: {
            module: "data-transfer", pageTitle: "Data Transfer" },
        component: () => import("@/views/admin/data-transfer/Index.vue"),
    },
    {
        path: "tax",
        name: "admin.tax-list",
        meta: {
            pageTitle: "Tax List",
        },
        component: () => import("@/views/admin/settings/tax/Index.vue"),
    },
    {
        path: "tax-group",
        name: "admin.tax-group-list",
        meta: {
            pageTitle: "Tax Groups",
        },
        component: () => import("@/views/admin/settings/tax-group/Index.vue"),
    },
    {
        path: "payment-mode",
        name: "admin.payment-mode-list",
        meta: {
            pageTitle: "Payment Modes",
        },
        component: () => import("@/views/admin/settings/payment-mode/Index.vue"),
    },
    {
        path: "ird-settings",
        name: "admin.ird-settings",
        meta: {pageTitle: "IRD EBS Settings"},
        component: () => import("@/views/admin/settings/ird/IrdSettings.vue"),
    },
    {
        path: "branches",
        name: "admin.branch-list",
        meta: {pageTitle: "Branch Management", allowWithoutBranch: true},
        component: () => import("@/views/admin/settings/branches/Index.vue"),
    },
    {
        path: "branches/:branchId/users",
        name: "admin.branch-users",
        meta: {pageTitle: "Branch User Assignments", allowWithoutBranch: true},
        component: () => import("@/views/admin/settings/branches/Users.vue"),
    },
    {
        path: "account-settings",
        name: "admin.account-settings",
        meta: {pageTitle: "Account Settings"},
        component: () => import("@/views/admin/accounting/account-setting/Index.vue"),
    },
    {
        path: "",
        redirect: "setting",
    },
];
