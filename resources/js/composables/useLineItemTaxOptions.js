import { computed } from 'vue';

/**
 * Options for line-item tax VSelect: "No tax" (empty id) + taxes from the store.
 * @param {import('vue').Ref<{ data?: Array<{ id: number | string, name: string }> }>} taxesRef
 */
export function useLineItemTaxOptions(taxesRef) {
    return computed(() => {
        const list = Array.isArray(taxesRef.value?.data) ? taxesRef.value.data : [];
        return [{ id: '', name: 'No tax' }, ...list];
    });
}
