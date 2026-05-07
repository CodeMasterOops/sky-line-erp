import { createRouter, createWebHistory } from 'vue-router';
import adminRoutes from '@/router/admin';
import superAdminRoutes from '@/router/super-admin.js';
import { useAdminAuthStore } from '@/stores/admin/auth';
import { useBranchStore } from '@/stores/admin/settings/branch.js';
import { useSuperAdminAuthStore } from '@/stores/super-admin/auth.js';
import { satisfiesAdminRoutePermission } from '@/helpers/checkPermission';
import { getAdminRoutePermission } from '@/router/adminRoutePermissions';

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

router.beforeEach(async (to, from, next) => {
    document.title = `${to.meta.pageTitle ?? ''}`;

    if (to.meta.isAdmin) {
        const adminAuth = useAdminAuthStore();
        const branchStore = useBranchStore();
        if (to.meta.requiresAuth && !adminAuth.authUser.access_token) {
            next({ name: 'admin.login' });
            return;
        }
        if (adminAuth.authUser.access_token && to.meta.isGuest) {
            next({ name: 'admin.dashboard' });
            return;
        }
        if (adminAuth.authUser.access_token && !to.meta.isGuest) {
            const requirement = getAdminRoutePermission(to.name);
            if (
                requirement !== undefined &&
                !satisfiesAdminRoutePermission(requirement)
            ) {
                next({ name: 'admin.dashboard' });
                return;
            }
        }

        if (
            adminAuth.authUser.access_token &&
            !to.meta.isGuest &&
            !to.meta.allowWithoutBranch
        ) {
            const selectedBranch = await branchStore.ensureSelectedBranchLoaded();
            if (!branchStore.selectedBranchId || !selectedBranch) {
                next({
                    name: 'admin.branch-select',
                    query: { redirect: to.fullPath },
                });
                return;
            }
        }

        next();
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
