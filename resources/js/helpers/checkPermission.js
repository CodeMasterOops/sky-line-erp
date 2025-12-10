import {useAdminAuthStore} from "@/stores/admin/auth";

export const hasPermission = (permission) => {
    const authStore = useAdminAuthStore();

    if (authStore.authUser.user_type === 'admin') {
        return true;
    }

    let permissions = Array.isArray(permission) ? permission : [permission];

    return permissions.every(p => authStore.authUser.permissions.includes(p));
}

export const permissionAccess = {
    mounted: (el, binding) => {
        if (!hasPermission(binding.value)) {
            el.parentNode.removeChild(el)
        }
    }
}
