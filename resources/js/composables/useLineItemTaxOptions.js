import { computed } from 'vue';

/**
 * Options for line-item tax VSelect: "No tax" + tax groups (prefixed group:X) + individual taxes.
 *
 * @param {import('vue').Ref<{ data?: Array<{ id: number|string, name: string, rate?: number }> }>} taxesRef
 * @param {import('vue').Ref<{ data?: Array<{ id: number|string, name: string }> }>} [taxGroupsRef]
 */
export function useLineItemTaxOptions(taxesRef, taxGroupsRef = null) {
    return computed(() => {
        const taxes = Array.isArray(taxesRef.value?.data) ? taxesRef.value.data : [];
        const groups = taxGroupsRef ? (Array.isArray(taxGroupsRef.value?.data) ? taxGroupsRef.value.data : []) : [];

        const groupOptions = groups.map((g) => ({
            id: `group:${g.id}`,
            name: `[Group] ${g.name}`,
        }));

        const taxOptions = taxes.map((t) => ({
            ...t,
            name: t.rate != null ? `${t.name} (${t.rate}%)` : t.name,
        }));

        return [{ id: '', name: 'No tax' }, ...groupOptions, ...taxOptions];
    });
}

/**
 * Parse a tax VSelect value into separate tax_id / tax_group_id fields for the API payload.
 *
 * @param {string|number|null} value - raw v-model value from the tax VSelect
 * @returns {{ tax_id: number|null, tax_group_id: number|null }}
 */
export function parseTaxSelection(value) {
    if (value && String(value).startsWith('group:')) {
        return { tax_id: null, tax_group_id: parseInt(String(value).replace('group:', ''), 10) };
    }
    return { tax_id: value ? parseInt(value, 10) : null, tax_group_id: null };
}

/**
 * Convert stored tax_id + tax_group_id fields back to the unified VSelect value.
 *
 * @param {{ tax_id?: number|null, tax_group_id?: number|null }} item
 * @returns {string|number}
 */
export function taxSelectionValue(item) {
    if (item.tax_group_id) {
        return `group:${item.tax_group_id}`;
    }
    return item.tax_id || '';
}
