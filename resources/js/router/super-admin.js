import AdminLayout from '@/layouts/super-admin/AppLayout.vue';
import superAdminSettingsRoutes from '@/router/modules/super-admin-settings.routes.js';

const routes = [
    {
        path: '/super-admin/login',
        name: 'super-admin.login',
        meta: {
            isGuest: true,
            isSuperAdmin: true,
            pageTitle: 'Login'
        },
        component: () => import('@/views/super-admin/auth/Login.vue')
    },
    {
        path: '/super-admin',
        component: AdminLayout,
        redirect: '/super-admin/dashboard',
        meta: {requiresAuth: true, isSuperAdmin: true},
        children: [
            {
                path: 'dashboard',
                name: 'super-admin.dashboard',
                meta: {
                    pageTitle: 'Dashboard'
                },
                component: () => import('@/views/super-admin/Dashboard.vue')
            },
            {
                path: 'profile',
                redirect: '/super-admin/settings/profile',
            },
            {
                path: 'setting',
                redirect: '/super-admin/settings/site-settings',
            },
            {
                path: 'setting/setting',
                redirect: '/super-admin/settings/site-settings',
            },
            {
                path: 'settings',
                component: () =>
                    import('@/views/super-admin/settings/settings-index.vue'),
                children: superAdminSettingsRoutes,
            },
            {
                path: 'fiscal-year',
                redirect: '/super-admin/settings/fiscal-year',
            },
            {
                path: 'company',
                name: 'super-admin.company-list',
                meta: {
                    pageTitle: 'Company List'
                },
                component: () => import('@/views/super-admin/company/Index.vue')
            },
            {
                path: 'company/create',
                name: 'super-admin.company-create',
                meta: {
                    pageTitle: 'Add Company'
                },
                component: () => import('@/views/super-admin/company/Create.vue')
            },
            {
                path: 'company/:id/edit',
                name: 'super-admin.company-edit',
                meta: {
                    pageTitle: 'Edit Company'
                },
                component: () => import('@/views/super-admin/company/Edit.vue')
            },
            {
                path: 'company/:id/provision-log',
                name: 'super-admin.company-provision-log',
                meta: {
                    pageTitle: 'Provision Log'
                },
                component: () => import('@/views/super-admin/company/ProvisionLog.vue')
            },
            {
                path: 'company/:id/modules',
                name: 'super-admin.company-modules',
                meta: {
                    pageTitle: 'Company Modules'
                },
                component: () => import('@/views/super-admin/company/Modules.vue')
            },
            {
                path: 'company-categories',
                name: 'super-admin.company-categories',
                meta: {
                    pageTitle: 'Company Categories'
                },
                component: () => import('@/views/super-admin/company-categories/Index.vue')
            },
            {
                path: 'subscription',
                name: 'super-admin.subscription',
                meta: {
                    pageTitle: 'Subscription'
                },
                component: () => import('@/views/super-admin/subscription/Index.vue')
            },
            {
                path: 'packages',
                name: 'super-admin.packages',
                meta: {
                    pageTitle: 'Packages'
                },
                component: () => import('@/views/super-admin/packages/Index.vue')
            },
            {
                path: 'currencies',
                name: 'super-admin.currency-list',
                meta: { pageTitle: 'Currencies' },
                component: () => import('@/views/super-admin/currencies/Index.vue')
            },
            {
                path: 'tax-templates',
                redirect: '/super-admin/settings/tax-templates',
            },
            {
                path: 'address',
                redirect: '/super-admin/settings/address',
            },
            {
                path: 'leads',
                name: 'super-admin.leads',
                meta: {pageTitle: 'Leads'},
                component: () => import('@/views/super-admin/leads/Index.vue'),
            },
            {
                path: 'leads/:id',
                name: 'super-admin.leads-show',
                meta: {pageTitle: 'Lead Detail'},
                component: () => import('@/views/super-admin/leads/Show.vue'),
            },
            {
                path: 'support',
                name: 'super-admin.support',
                meta: {pageTitle: 'Support'},
                component: () => import('@/views/super-admin/support/Index.vue'),
            },
        ]
    },
];

export default routes;
