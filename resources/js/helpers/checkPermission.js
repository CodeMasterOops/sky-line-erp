import {useAdminAuthStore} from "@/stores/admin/auth";

export const hasPermission = (permission) => {
    const authStore = useAdminAuthStore();

    if (authStore.authUser.user_type === 'admin') {
        return true;
    }

    let permissions = Array.isArray(permission) ? permission : [permission];

    return permissions.every(p => authStore.authUser.permissions.includes(p));
}

/** User must have at least one of the given permissions (OR). */
export const hasAnyPermission = (permissions) => {
    const authStore = useAdminAuthStore();
    if (authStore.authUser.user_type === 'admin') {
        return true;
    }
    const list = Array.isArray(permissions) ? permissions : [permissions];
    return list.some((p) => authStore.authUser.permissions.includes(p));
}

/**
 * @param {null|string|{any: string[]}|undefined} requirement
 * @returns {boolean}
 */
export const satisfiesAdminRoutePermission = (requirement) => {
    if (requirement === null || requirement === undefined) {
        return true;
    }
    if (typeof requirement === 'string') {
        return hasPermission(requirement);
    }
    if (requirement && Array.isArray(requirement.any)) {
        return hasAnyPermission(requirement.any);
    }
    return true;
}

export const permissionAccess = {
    mounted: (el, binding) => {
        if (!hasPermission(binding.value)) {
            el.parentNode.removeChild(el)
        }
    }
}
