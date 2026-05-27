export default [
    {
        path: "site-settings",
        name: "super-admin.setting",
        component: () =>
            import("@/views/super-admin/settings/SiteSettings.vue"),
        meta: { pageTitle: "Site Settings" },
    },
    {
        path: "security-settings",
        name: "super-admin.security-settings",
        component: () =>
            import("@/views/super-admin/settings/security-settings.vue"),
        meta: { pageTitle: "Security Settings" },
    },
    {
        path: "profile",
        name: "super-admin.profile",
        meta: { pageTitle: "Profile" },
        component: () => import("@/views/super-admin/profile/Index.vue"),
    },
    {
        path: "fiscal-year",
        name: "super-admin.fiscal-year-list",
        meta: { pageTitle: "Fiscal Year" },
        component: () => import("@/views/super-admin/fiscal-year/Index.vue"),
    },
    {
        path: "tax-templates",
        name: "super-admin.tax-templates",
        meta: { pageTitle: "Tax Templates" },
        component: () => import("@/views/super-admin/tax-templates/Index.vue"),
    },
    {
        path: "address",
        name: "super-admin.address",
        meta: { pageTitle: "Address reference" },
        component: () => import("@/views/super-admin/address/Index.vue"),
    },
    {
        path: "",
        redirect: { name: "super-admin.setting" },
    },
];
