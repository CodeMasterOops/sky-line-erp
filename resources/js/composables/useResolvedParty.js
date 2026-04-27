import {computed, unref} from 'vue';

function resolvePartyList(partiesList) {
    const raw = unref(partiesList);
    if (Array.isArray(raw)) {
        return raw;
    }
    if (raw && Array.isArray(raw.data)) {
        return raw.data;
    }
    return [];
}

/**
 * Resolves the selected party for display from the multiselect list, with
 * optional fallback from the loaded document (invoice/bill) when the list
 * does not include the current party row.
 *
 * @param {import('vue').Ref|import('vue').ComputedRef|string|number} partyId
 * @param {import('vue').Ref|import('vue').ComputedRef|object[]|{ data: object[] }} partiesList
 *   Pinia `parties` from storeToRefs, or a plain array / ref to array
 * @param {import('vue').Ref|import('vue').ComputedRef<object|undefined|null>} [documentParty]
 * @returns {import('vue').ComputedRef<object|null>}
 */
export function useResolvedParty(partyId, partiesList, documentParty) {
    return computed(() => {
        const id = unref(partyId);
        if (id == null || id === '') {
            return null;
        }
        const fromList = resolvePartyList(partiesList).find((p) => String(p.id) === String(id));
        const docParty = documentParty != null ? unref(documentParty) : null;
        const docMatches = docParty && String(docParty.id) === String(id);
        if (fromList) {
            if (docMatches && (docParty.pan || docParty.address)) {
                return {...fromList, ...docParty};
            }
            return fromList;
        }
        if (docMatches) {
            return docParty;
        }
        return null;
    });
}
