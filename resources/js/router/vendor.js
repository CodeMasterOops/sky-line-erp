import AppLayout from '@/layouts/vendor/AppLayout.vue';

const routes = [
    {
        path: '/vendor/login',
        name: 'vendor.login',
        meta: {
            isGuest: true,
            isVendor: true,
            pageTitle: 'Login'
        },
        component: () => import('@/views/vendor/auth/Login.vue')
    },
    {
        path: '/vendor',
        component: AppLayout,
        redirect: '/vendor/dashboard',
        meta: {requiresAuth: true, isVendor: true},
        children: [
            {
                path: 'dashboard',
                name: 'vendor.dashboard',
                meta: {
                    pageTitle: 'Dashboard'
                },
                component: () => import('@/views/vendor/Dashboard.vue')
            },
            {
                path: 'profile',
                name: 'vendor.profile',
                meta: {
                    pageTitle: 'Profile Update'
                },
                component: () => import('@/views/vendor/profile/Index.vue')
            },
            {
                path: 'notification',
                name: 'vendor.notification-list',
                meta: {
                    pageTitle: 'Notifications'
                },
                component: () => import('@/views/vendor/notification/Index.vue')
            },
            {
                path: 'setting',
                children: [
                    {
                        path: 'setting',
                        name: 'vendor.setting',
                        meta: {
                            pageTitle: 'Site Setting'
                        },
                        component: () => import('@/views/vendor/Setting.vue')
                    },
                ]
            },
            {
                path: 'product',
                name: 'vendor.product-list',
                meta: {
                    pageTitle: 'Product List'
                },
                component: () => import('@/views/vendor/product/Index.vue')
            },
            {
                path: 'product/create',
                name: 'vendor.product-create',
                meta: {
                    pageTitle: 'Add Product'
                },
                component: () => import('@/views/vendor/product/Create.vue')
            },
            {
                path: 'product/:id/edit',
                name: 'vendor.product-edit',
                meta: {
                    pageTitle: 'Edit Product'
                },
                component: () => import('@/views/vendor/product/Edit.vue')
            },
            {
                path: 'product/:id',
                name: 'vendor.product-show',
                meta: {
                    pageTitle: 'Product Detail'
                },
                component: () => import('@/views/vendor/product/Show.vue')
            },
            {
                path: 'stock',
                name: 'vendor.stock-list',
                meta: {
                    pageTitle: 'Manage Stock'
                },
                component: () => import('@/views/vendor/stock/Index.vue')
            },
            {
                path: 'stock/:id/history',
                name: 'vendor.stock-history',
                meta: {
                    pageTitle: 'Stock History'
                },
                component: () => import('@/views/vendor/stock/History.vue')
            },
        ]
    },
];

export default routes;
