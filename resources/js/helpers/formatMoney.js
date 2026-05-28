/** Base currency symbol (matches config/currency.php and CurrencySeeder). */
export const BASE_CURRENCY_SYMBOL = 'Rs.';

/**
 * Format a numeric value as plain number string with 2 decimal places (no symbol).
 *
 * @param {number|string|null|undefined} value
 * @returns {string}
 */
export function formatMoneyPlain(value) {
    if (value === undefined || value === null || value === '') {
        return '—';
    }

    const n = Number(value);

    if (Number.isNaN(n)) {
        return '—';
    }

    return n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/**
 * Format a numeric value as a money string with base currency symbol.
 *
 * @param {number|string|null|undefined} value
 * @param {{ symbol?: string }} [options]
 * @returns {string}
 */
export function formatMoney(value, options = {}) {
    if (value === undefined || value === null || value === '') {
        return '—';
    }

    const n = Number(value);

    if (Number.isNaN(n)) {
        return '—';
    }

    const symbol = options.symbol ?? BASE_CURRENCY_SYMBOL;
    const formatted = n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    return `${symbol} ${formatted}`;
}

/**
 * Format a signed amount: negatives shown in parentheses, e.g. (Rs. 1,234.56).
 *
 * @param {number|string|null|undefined} value
 * @param {{ symbol?: string }} [options]
 * @returns {string}
 */
export function formatSignedAmount(value, options = {}) {
    if (value === undefined || value === null || value === '') {
        return '—';
    }

    const n = Number(value);

    if (Number.isNaN(n)) {
        return '—';
    }

    if (n < 0) {
        return `(${formatMoney(Math.abs(n), options)})`;
    }

    return formatMoney(n, options);
}
