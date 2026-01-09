import { createRouter, createWebHistory } from 'vue-router';
import adminRoutes from '@/router/admin';
import superAdminRoutes from '@/router/super-admin.js';
import { useAdminAuthStore } from '@/stores/admin/auth';
import { useSuperAdminAuthStore } from '@/stores/super-admin/auth.js';

const routes = [
    ...superAdminRoutes,
    ...adminRoutes,
    {
        path: '/:pathMatch(.*)*'
        //component: () => import("@/views/404.vue"),
    }
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: 'active'
});

router.beforeEach((to, from, next) => {
    document.title = `${to.meta.pageTitle ?? ''}`;

    if (to.meta.isAdmin) {
        if (to.meta.requiresAuth && !useAdminAuthStore().authUser.access_token) {
            next({ name: 'admin.login' });
        } else if (useAdminAuthStore().authUser.access_token && to.meta.isGuest) {
            next({ name: 'admin.dashboard' });
        } else {
            next();
        }
    } else if (to.meta.isSuperAdmin) {
        if (to.meta.requiresAuth && !useSuperAdminAuthStore().authUser.access_token) {
            next({ name: 'super-admin.login' });
        } else if (useSuperAdminAuthStore().authUser.access_token && to.meta.isGuest) {
            next({ name: 'super-admin.dashboard' });
        } else {
            next();
        }
    } else {
        next();
    }
});

export default router;
