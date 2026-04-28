/**
 * Format a numeric value as a money string with 2 decimal places.
 * Returns '—' for null / undefined / empty / non-numeric values.
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatMoney(value) {
    if (value === undefined || value === null || value === '') return '—';
    const n = Number(value);
    if (Number.isNaN(n)) return '—';
    return n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
