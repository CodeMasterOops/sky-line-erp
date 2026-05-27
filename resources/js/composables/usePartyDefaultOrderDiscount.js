import {unref, watch} from 'vue';

/**
 * When a customer with a default order discount is selected, pre-fill order discount fields.
 * User can still override before save.
 *
 * @param {import('vue').Ref|import('vue').ComputedRef|string|number} partyId
 * @param {import('vue').ComputedRef<object|null>} resolvedParty
 * @param {object} form - reactive form with order_discount_type / order_discount_value
 * @param {{ skipInitial?: boolean }} [options] - set skipInitial on Edit forms to avoid overwriting loaded document discount
 */
export function usePartyDefaultOrderDiscount(partyId, resolvedParty, form, options = {}) {
    let skipInitial = options.skipInitial ?? false;

    watch(
        () => unref(partyId),
        (newId, oldId) => {
            if (!newId) {
                return;
            }

            if (skipInitial && oldId === undefined) {
                skipInitial = false;

                return;
            }

            const party = unref(resolvedParty);
            if (!party) {
                return;
            }

            const value = parseFloat(party.discount_value);
            if (!Number.isFinite(value) || value <= 0) {
                return;
            }

            form.order_discount_type = party.discount_type || 'fixed';
            form.order_discount_value = String(value);
        },
    );
}
