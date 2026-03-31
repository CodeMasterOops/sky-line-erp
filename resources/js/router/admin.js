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
                name: "admin.profile",
                meta: {
                    pageTitle: "Profile Update",
                },
                component: () => import("@/views/admin/profile/Index.vue"),
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
                        path: "", // default child route
                        redirect: "general-settings", // redirect /admin/settings -> /admin/settings/general-settings
                    },
                ],
            },

            {
                path: "financial-settings",
                meta: {
                    pageTitle: "Financial Settings",
                },
                component: () =>
                    import("@/views/admin/settings/financial-settings/financial-settings.vue"),
                children: [
                    {
                        path: "tax-rates",
                        name: "admin.tax-rates",
                        component: () =>
                            import("@/views/admin/settings/financial-settings/tax-rates.vue"),
                        meta: {pageTitle: "Tax Rates"},
                    },
                    {
                        path: "",
                        name: "admin.financial-settings",
                        meta: {
                            pageTitle: "Financial Settings",
                        },
                        redirect: "/admin/financial-settings",
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
                    pageTitle: "Sales List",
                },
                component: () => import("@/views/admin/sales/list/Index.vue"),
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
            // {
            //     path: 'tax',
            //     name: 'admin.tax-list',
            //     meta: {
            //         pageTitle: 'Tax List'
            //     },
            //     component: () => import('@/views/admin/setting/tax/Index.vue')
            // },
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
                path: "product",
                name: "admin.product-list",
                meta: {
                    pageTitle: "Product List",
                },
                component: () =>
                    import("@/views/admin/inventory/product/ProductListV2.vue"),
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
                path: "warranty",
                name: "admin.warranty-list",
                meta: {
                    pageTitle: "Warranty",
                },
                component: () =>
                    import("@/views/admin/inventory/warranty/Index.vue"),
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
                path: "purchase-returns",
                name: "admin.purchase-returns",
                meta: {
                    pageTitle: "Purchase Returns",
                },
                component: () =>
                    import("@/views/admin/purchase/returns/Index.vue"),
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
                path: "account-settings",
                name: "admin.account-settings",
                meta: {
                    pageTitle: "Account Settings",
                },
                component: () =>
                    import("@/views/admin/accounting/account-setting/Index.vue"),
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
        ],
    },
];

export default routes;
