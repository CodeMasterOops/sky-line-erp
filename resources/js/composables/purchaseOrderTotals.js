/**
 * Shared purchase order math: line + order (fixed/%) discounts and pro-rata tax base.
 * Mirrors server rules in App\Services\Purchase\PurchaseOrderTotalsCalculator.
 */

/** Short label for discount-type toggle buttons (input-group dropdowns). */
export function lineDiscountTypeLabel(type) {
    return type === 'percent' ? '%' : 'Fixed';
}

export function resolveLineDiscountMoney(gross, type, value) {
    const g = Number(gross) || 0;
    if (g <= 0) {
        return 0;
    }
    const t = type === 'percent' ? 'percent' : 'fixed';
    if (t === 'percent') {
        const pct = Math.min(100, Math.max(0, Number(value) || 0));
        return Math.min((g * pct) / 100, g);
    }
    return Math.min(Math.max(0, Number(value) || 0), g);
}

export function lineGross(item) {
    return (Number(item.quantity) || 0) * (Number(item.rate) || 0);
}

export function lineDiscountMoneyFromItem(item) {
    return resolveLineDiscountMoney(
        lineGross(item),
        item.line_discount_type || 'fixed',
        item.line_discount_value
    );
}

export function lineNetFromItem(item) {
    return Math.max(0, lineGross(item) - lineDiscountMoneyFromItem(item));
}

export function orderDiscountMoney(sumLineNet, type, value) {
    const s = Number(sumLineNet) || 0;
    if (s <= 0) {
        return 0;
    }
    const t = type === 'percent' ? 'percent' : 'fixed';
    if (t === 'percent') {
        const pct = Math.min(100, Math.max(0, Number(value) || 0));
        return Math.min((s * pct) / 100, s);
    }
    return Math.min(Math.max(0, Number(value) || 0), s);
}

export function buildOrderAllocations(lineNets, orderDiscountAmount) {
    const list = lineNets || [];
    const sum = list.reduce((a, b) => a + (Number(b) || 0), 0);
    const o = Math.max(0, Number(orderDiscountAmount) || 0);
    if (sum <= 0) {
        return list.map(() => 0);
    }
    return list.map((n) => o * ((Number(n) || 0) / sum));
}

/**
 * Merged per-line fixed discount for purchase bills (no order-level row).
 * @param {Array<{ quantity: *, rate: *, discount_amount: * }>} poItems
 * @param {number|string|undefined} orderDiscountAmount
 * @returns {number[]}
 */
export function mergePoOrderDiscountIntoLineDiscounts(poItems, orderDiscountAmount) {
    const lineNets = (poItems || []).map((item) => {
        const g = (Number(item.quantity) || 0) * (Number(item.rate) || 0);
        const ld = Number(item.discount_amount) || 0;
        return Math.max(0, g - ld);
    });
    const o = Math.max(0, Number(orderDiscountAmount) || 0);
    const allocs = buildOrderAllocations(lineNets, o);
    return (poItems || []).map((item, i) => (Number(item.discount_amount) || 0) + (allocs[i] || 0));
}

/**
 * Per-line total after pro-rata order discount + line tax (matches stored line tax).
 * @param {number|undefined} [lineTax] — pass calcLineTax(item, index) for live UI; else uses item.tax_amount
 */
export function lineItemTotalForDisplay(item, index, items, orderDiscountAmount, lineTax) {
    const lineNets = (items || []).map((it) => lineNetFromItem(it));
    const o = Math.max(0, Number(orderDiscountAmount) || 0);
    const allocs = buildOrderAllocations(lineNets, o);
    const after = Math.max(0, (lineNets[index] || 0) - (allocs[index] || 0));
    const tax =
        lineTax !== undefined && lineTax !== null
            ? Number(lineTax)
            : Number(item.tax_amount) || 0;
    return after + tax;
}
