/**
 * Format amounts for super-admin UI (NPR / Rs.).
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatSuperAdminMoney(value) {
    if (value === undefined || value === null || value === '') {
        return 'Rs. 0.00';
    }

    const amount = Number(value);

    if (Number.isNaN(amount)) {
        return '—';
    }

    return `Rs. ${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
}
