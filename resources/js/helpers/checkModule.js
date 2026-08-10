import { getActivePinia } from 'pinia';
import { useAdminAuthStore } from '@/stores/admin/auth';

/**
 * Module gating for the SPA — the client-side mirror of the `module`
 * middleware. Hides what a company does not run; it is not a security control
 * (the server refuses the request regardless), so it fails *open*: while the
 * module list is still loading, nothing is hidden and no navigation is blocked.
 *
 * @see resources/js/stores/admin/auth.js — `authUser.modules`
 * @see app/Http/Middleware/EnsureModuleEnabled.php
 */

function resolveAuthStore() {
    const pinia = getActivePinia();

    return pinia ? useAdminAuthStore(pinia) : null;
}

/**
 * @param {string|undefined|null} moduleKey
 * @returns {boolean}
 */
export const isModuleEnabled = (moduleKey) => {
    if (!moduleKey) {
        return true;
    }

    const authStore = resolveAuthStore();

    if (!authStore) {
        return true;
    }

    const modules = authStore.authUser.modules;

    // Not loaded yet: show everything rather than flashing a half-empty menu
    // on the way in. The first refreshPermissions() settles it.
    if (!Array.isArray(modules) || modules.length === 0) {
        return true;
    }

    return modules.includes(moduleKey);
};

/**
 * Directive form: `v-module="'crm'"` removes the element when the company does
 * not run that module. Mirrors `v-permission-access`.
 */
export const moduleAccess = {
    mounted: (el, binding) => {
        if (!isModuleEnabled(binding.value)) {
            el.parentNode?.removeChild(el);
        }
    },
};
