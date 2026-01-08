import AdminLayout from '@/layouts/admin/AppLayout.vue';

const routes = [
    {
        path:'/',
        redirect: '/admin/dashboard'
    },
    {
        path: '/admin/login',
        name: 'admin.login',
        meta: {
            isGuest: true,
            isAdmin: true,
            pageTitle: 'Login'
        },
        component: () => import('@/views/admin/auth/Login.vue')
    },
    {
        path: '/admin',
        component: AdminLayout,
        redirect: '/admin/dashboard',
        meta: {requiresAuth: true, isAdmin: true},
        children: [
            {
                path: 'dashboard',
                name: 'admin.dashboard',
                meta: {
                    pageTitle: 'Dashboard'
                },
                component: () => import('@/views/admin/Dashboard.vue')
            },
            {
                path: 'profile',
                name: 'admin.profile',
                meta: {
                    pageTitle: 'Profile Update'
                },
                component: () => import('@/views/admin/profile/Index.vue')
            },
            {
                path: 'setting',
                children: [
                    {
                        path: 'setting',
                        name: 'admin.setting',
                        meta: {
                            pageTitle: 'Site Setting'
                        },
                        component: () => import('@/views/admin/Setting.vue')
                    }
                ]
            },
            {
                path: 'user-management',
                redirect: '/admin/user-management/user',
                children: [
                    {
                        path: 'role',
                        name: 'admin.role-list',
                        meta: {
                            pageTitle: 'Role List'
                        },
                        component: () => import('@/views/admin/user-management/role/Index.vue')
                    },
                    {
                        path: 'role/create',
                        name: 'admin.role-create',
                        meta: {
                            pageTitle: 'Add Role'
                        },
                        component: () => import('@/views/admin/user-management/role/Create.vue')
                    },
                    {
                        path: 'role/:id/edit',
                        name: 'admin.role-edit',
                        meta: {
                            pageTitle: 'Edit Role'
                        },
                        component: () => import('@/views/admin/user-management/role/Edit.vue')
                    },
                    {
                        path: 'user',
                        name: 'admin.user-list',
                        meta: {
                            pageTitle: 'User List'
                        },
                        component: () => import('@/views/admin/user-management/user/Index.vue')
                    }
                ]
            },
            {
                path: 'notification',
                name: 'admin.notification-list',
                meta: {
                    pageTitle: 'Notifications'
                },
                component: () => import('@/views/admin/notification/Index.vue')
            },
            {
                path: 'brand',
                name: 'admin.brand-list',
                meta: {
                    pageTitle: 'Brand List'
                },
                component: () => import('@/views/admin/inventory/brand/Index.vue')
            },
            {
                path: 'unit',
                name: 'admin.unit-list',
                meta: {
                    pageTitle: 'Unit List'
                },
                component: () => import('@/views/admin/inventory/unit/Index.vue')
            },
            {
                path: 'warehouse',
                name: 'admin.warehouse-list',
                meta: {
                    pageTitle: 'Warehouse List'
                },
                component: () => import('@/views/admin/inventory/warehouse/Index.vue')
            },
            {
                path: 'tax',
                name: 'admin.tax-list',
                meta: {
                    pageTitle: 'Tax List'
                },
                component: () => import('@/views/admin/setting/tax/Index.vue')
            },
            {
                path: 'product-category',
                name: 'admin.product-category-list',
                meta: {
                    pageTitle: 'Product Category'
                },
                component: () => import('@/views/admin/inventory/product-category/Index.vue')
            },
            {
                path: 'product',
                name: 'admin.product-list',
                meta: {
                    pageTitle: 'Product List'
                },
                component: () => import('@/views/admin/inventory/product/Index.vue')
            },
        ]
    },
];

export default routes;
