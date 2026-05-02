import AdminLayout from "@/layouts/admin/AppLayout.vue";
import accountingRoutes from "@/router/modules/accounting.routes.js";
import hrRoutes from "@/router/modules/hr.routes.js";
import inventoryRoutes from "@/router/modules/inventory.routes.js";
import purchaseRoutes from "@/router/modules/purchase.routes.js";
import salesRoutes from "@/router/modules/sales.routes.js";
import settingsRoutes from "@/router/modules/settings.routes.js";
import userManagementRoutes from "@/router/modules/user-management.routes.js";

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
                children: settingsRoutes,
            },

            //user management module
            ...userManagementRoutes,

            {
                path: "notification",
                name: "admin.notification-list",
                meta: {
                    pageTitle: "Notifications",
                },
                component: () => import("@/views/admin/notification/Index.vue"),
            },
            //sales module
            ...salesRoutes,
            {
                path: "reports",
                name: "admin.reports-hub",
                meta: {
                    pageTitle: "Reports",
                },
                component: () => import("@/views/admin/reports/ReportsHub.vue"),
            },
            //inventory module
            ...inventoryRoutes,
            {
                path: "party",
                name: "admin.party-list",
                meta: {
                    pageTitle: "Party List",
                },
                component: () => import("@/views/admin/party/Index.vue"),
            },
            //purchase module
            ...purchaseRoutes,

            //accounting module
            ...accountingRoutes,
            
            //hr module
            ...hrRoutes,
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
