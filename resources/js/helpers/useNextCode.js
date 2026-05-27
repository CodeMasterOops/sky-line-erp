import { ref, unref } from 'vue';
import { apiAdmin } from '@/helpers/api.js';
import showErrors from '@/helpers/showErrors.js';

/**
 * Fetch the next sequential entity code from the backend.
 *
 * @param {import('vue').Ref|object} form - reactive form object or ref
 * @param {string} codeField - form field name (default: 'code')
 * @param {string} endpoint - API path e.g. 'product/next-code'
 * @param {(field: string) => void} [validateField] - optional yup validate callback
 */
export function useNextCode(form, codeField = 'code', endpoint, validateField = null) {
    const loading = ref(false);

    async function fetchNextCode() {
        loading.value = true;
        try {
            const res = await apiAdmin(endpoint);
            const code = res.data?.data?.code ?? '';
            const target = unref(form);
            if (target && typeof target === 'object') {
                target[codeField] = code;
            }
            if (typeof validateField === 'function') {
                validateField(codeField);
            }
        } catch (e) {
            showErrors(e);
        } finally {
            loading.value = false;
        }
    }

    return { loading, fetchNextCode };
}
