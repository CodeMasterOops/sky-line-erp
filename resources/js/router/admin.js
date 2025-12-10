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
                path: 'customer',
                name: 'admin.customer-list',
                meta: {
                    pageTitle: 'Customer'
                },
                component: () => import('@/views/admin/customer/Index.vue')
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
                path: 'vendor',
                name: 'admin.vendor-list',
                meta: {
                    pageTitle: 'Vendor List'
                },
                component: () => import('@/views/admin/vendor/Index.vue')
            },
            {
                path: 'vendor/create',
                name: 'admin.vendor-create',
                meta: {
                    pageTitle: 'Add Vendor'
                },
                component: () => import('@/views/admin/vendor/Create.vue')
            },
            {
                path: 'vendor/:id/edit',
                name: 'admin.vendor-edit',
                meta: {
                    pageTitle: 'Edit Vendor'
                },
                component: () => import('@/views/admin/vendor/Edit.vue')
            },
            {
                path: 'product-category',
                name: 'admin.product-category-list',
                meta: {
                    pageTitle: 'Product Category List'
                },
                component: () => import('@/views/admin/product-category/Index.vue')
            },
            {
                path: 'product-category/create',
                name: 'admin.product-category-create',
                meta: {
                    pageTitle: 'Add Product Category'
                },
                component: () => import('@/views/admin/product-category/Create.vue')
            },
            {
                path: 'product-category/:id/edit',
                name: 'admin.product-category-edit',
                meta: {
                    pageTitle: 'Edit Product Category'
                },
                component: () => import('@/views/admin/product-category/Edit.vue')
            },
            {
                path: 'brand',
                name: 'admin.brand-list',
                meta: {
                    pageTitle: 'Brand List'
                },
                component: () => import('@/views/admin/brand/Index.vue')
            },
            {
                path: 'product',
                name: 'admin.product-list',
                meta: {
                    pageTitle: 'Product List'
                },
                component: () => import('@/views/admin/product/Index.vue')
            },
            {
                path: 'product/create',
                name: 'admin.product-create',
                meta: {
                    pageTitle: 'Add Product'
                },
                component: () => import('@/views/admin/product/Create.vue')
            },
            {
                path: 'product/:id/edit',
                name: 'admin.product-edit',
                meta: {
                    pageTitle: 'Edit Product'
                },
                component: () => import('@/views/admin/product/Edit.vue')
            },
            {
                path: 'product/:id',
                name: 'admin.product-show',
                meta: {
                    pageTitle: 'Product Detail'
                },
                component: () => import('@/views/admin/product/Show.vue')
            },
            {
                path: 'stock',
                name: 'admin.stock-list',
                meta: {
                    pageTitle: 'Manage Stock'
                },
                component: () => import('@/views/admin/stock/Index.vue')
            },
            {
                path: 'stock/:id/history',
                name: 'admin.stock-history',
                meta: {
                    pageTitle: 'Stock History'
                },
                component: () => import('@/views/admin/stock/History.vue')
            },
        ]
    },
];

export default routes;
