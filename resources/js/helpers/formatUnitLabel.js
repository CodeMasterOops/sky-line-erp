/**
 * @param {string|{ name?: string }|null|undefined} unit
 */
export function formatUnitLabel(unit) {
    if (!unit) {
        return '';
    }

    if (typeof unit === 'string') {
        return unit;
    }

    if (typeof unit === 'object' && unit.name) {
        return unit.name;
    }

    return '';
}

/**
 * @param {{ product_variant?: { unit?: { name?: string } }, unit?: string|{ name?: string } }} item
 */
export function itemUnitLabel(item) {
    return formatUnitLabel(item?.product_variant?.unit)
        || formatUnitLabel(item?.unit)
        || '—';
}
