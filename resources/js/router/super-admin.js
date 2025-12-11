import AdminLayout from '@/layouts/super-admin/AppLayout.vue';

const routes = [
    {
        path:'/',
        redirect: '/super-admin/dashboard'
    },
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
                name: 'super-admin.profile',
                meta: {
                    pageTitle: 'Profile Update'
                },
                component: () => import('@/views/super-admin/profile/Index.vue')
            },
            {
                path: 'setting',
                children: [
                    {
                        path: 'setting',
                        name: 'super-admin.setting',
                        meta: {
                            pageTitle: 'Site Setting'
                        },
                        component: () => import('@/views/super-admin/Setting.vue')
                    }
                ]
            },
            {
                path: 'fiscal-year',
                name: 'super-admin.fiscal-year-list',
                meta: {
                    pageTitle: 'Fiscal Year'
                },
                component: () => import('@/views/super-admin/fiscal-year/Index.vue')
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
        ]
    },
];

export default routes;
