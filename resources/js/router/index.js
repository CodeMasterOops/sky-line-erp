import {createRouter, createWebHistory} from 'vue-router'
import adminRoutes from '@/router/admin';
import vendorRoutes from '@/router/vendor';
import {useAdminAuthStore} from "@/stores/admin/auth";
import {useVendorAuthStore} from "@/stores/vendor/auth.js";

const routes = [
    ...adminRoutes,
    ...vendorRoutes,
    {
        path: "/:pathMatch(.*)*",
        //component: () => import("@/views/404.vue"),
    },
]

const router = createRouter({
    history: createWebHistory(),
    routes,
    linkActiveClass: "active",
});

router.beforeEach((to, from, next) => {
    document.title = `${to.meta.pageTitle ?? ''}`;
    
    if (to.meta.isAdmin) {
        if (to.meta.requiresAuth && !useAdminAuthStore().authUser.access_token) {
            next({name: "admin.login"});
        } else if (useAdminAuthStore().authUser.access_token && to.meta.isGuest) {
            next({name: "admin.dashboard"});
        }
    }
    else if (to.meta.isVendor) {
        if (to.meta.requiresAuth && !useVendorAuthStore().authUser.access_token) {
            next({name: "vendor.login"});
        } else if (useVendorAuthStore().authUser.access_token && to.meta.isGuest) {
            next({name: "vendor.dashboard"});
        }
    }
    next();
});

export default router
