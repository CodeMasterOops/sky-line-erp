import { ref } from 'vue';
import { apiAdmin } from '@/helpers/api';

/**
 * The shipped module registry — labels, icons and groups — fetched once per
 * page load and shared by every caller.
 *
 * This is presentation data about modules the *platform* has, not about the
 * ones this company runs (that is `authUser.modules` / `isModuleEnabled`). It
 * exists so screens can name a module they are not allowed to open without the
 * frontend keeping a hand-maintained copy of `config/modules.php`.
 *
 * @see app/Http/Controllers/Api/Admin/ModuleController.php — `catalogue()`
 */

const modules = ref([]);
const loaded = ref(false);
let inFlight = null;

function humanise(key) {
    return String(key)
        .split('-')
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(' ');
}

export function useModuleCatalogue() {
    /**
     * Fetch the catalogue once. Concurrent callers share the same request, and
     * a failure is swallowed — a missing label falls back to a humanised key,
     * which is never worth an error toast.
     */
    function load() {
        if (loaded.value) {
            return Promise.resolve(modules.value);
        }

        if (!inFlight) {
            inFlight = apiAdmin('module/catalogue', 'get')
                .then((res) => {
                    modules.value = Array.isArray(res.data?.data) ? res.data.data : [];
                    loaded.value = true;
                    return modules.value;
                })
                .catch(() => [])
                .finally(() => {
                    inFlight = null;
                });
        }

        return inFlight;
    }

    /**
     * @param {string} key
     * @returns {string} the module's display name, or a humanised key
     */
    function moduleLabel(key) {
        if (!key) {
            return 'This module';
        }

        return modules.value.find((m) => m.key === key)?.name ?? humanise(key);
    }

    function moduleIcon(key) {
        return modules.value.find((m) => m.key === key)?.icon ?? 'ti ti-plug-connected-x';
    }

    return { modules, loaded, load, moduleLabel, moduleIcon };
}
