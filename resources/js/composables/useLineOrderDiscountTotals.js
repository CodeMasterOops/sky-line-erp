/**
 * Shared Vue composable for purchase bill / PO line + order discount math and tax-on-net.
 * Uses pure helpers from purchaseOrderTotals.js (server-aligned).
 *
 * Supports tax-inclusive pricing: when form.tax_inclusive === true, the entered rate already
 * includes tax. Tax is back-calculated: taxAmount = taxable * rate / (100 + rate).
 * Grand total stays at qty*rate (no tax added on top).
 */
import { computed } from 'vue';
import {
    buildOrderAllocations,
    lineDiscountMoneyFromItem,
    lineNetFromItem,
    orderDiscountMoney,
} from '@/composables/purchaseOrderTotals.js';

/**
 * @param {object} options
 * @param {import('vue').Reactive<object>} options.form - reactive form with `items`, `order_discount_type`, `order_discount_value`, `tax_inclusive`
 * @param {import('vue').Ref<{ data?: Array<{ id: number, rate: * }> }>} options.taxes - taxes store ref
 */
export function useLineOrderDiscountTotals({ form, taxes }) {
    const getTaxRate = (taxId) => {
        if (!taxId) {
            return 0;
        }
        const numericId = parseInt(taxId, 10);
        const list = taxes.value?.data;
        if (!Array.isArray(list)) {
            return 0;
        }
        const tax = list.find((t) => t.id === numericId);
        return tax ? Number(tax.rate || 0) : 0;
    };

    const isInclusive = () => !!form.tax_inclusive;

    const orderLevelComputed = computed(() => {
        const nets = form.items.map((it) => lineNetFromItem(it));
        const sumLineNet = nets.reduce((a, b) => a + b, 0);
        const orderDisc = orderDiscountMoney(
            sumLineNet,
            form.order_discount_type || 'fixed',
            form.order_discount_value
        );
        const allocs = buildOrderAllocations(nets, orderDisc);
        return { nets, sumLineNet, orderDisc, allocs };
    });

    const calcLineTax = (item, index) => {
        const { nets, allocs } = orderLevelComputed.value;
        const lineNet = nets[index] ?? 0;
        const alloc = allocs[index] || 0;
        const taxable = Math.max(0, lineNet - alloc);
        const taxRate = getTaxRate(item.tax_id);
        if (taxRate === 0) {
            return 0;
        }
        if (isInclusive()) {
            // Back-calculate: tax is embedded in the taxable amount
            return taxable * taxRate / (100 + taxRate);
        }
        return taxable * (taxRate / 100);
    };

    const summary = computed(() => {
        let subtotalGross = 0;
        let lineDiscount = 0;
        let tax = 0;
        let nonTaxableBase = 0;
        let taxableBase = 0;

        const { nets, allocs, orderDisc } = orderLevelComputed.value;
        const sumLineNet = nets.reduce((a, b) => a + b, 0);
        const inclusive = isInclusive();

        form.items.forEach((item, index) => {
            const g = (Number(item.quantity) || 0) * (Number(item.rate) || 0);
            const ld = lineDiscountMoneyFromItem(item);
            subtotalGross += g;
            lineDiscount += ld;
            tax += calcLineTax(item, index);

            const lineNet = nets[index] ?? 0;
            const alloc = allocs[index] || 0;
            const afterOrder = Math.max(0, lineNet - alloc);
            const r = getTaxRate(item.tax_id);
            if (r > 0) {
                taxableBase += afterOrder;
            } else {
                nonTaxableBase += afterOrder;
            }
        });

        // For inclusive pricing, grand total = net (tax already embedded, not added on top)
        const grandTotal = inclusive
            ? sumLineNet - orderDisc
            : sumLineNet - orderDisc + tax;
        const totalDiscountAmount = lineDiscount + orderDisc;

        return {
            subtotal: subtotalGross.toFixed(2),
            lineDiscount: lineDiscount.toFixed(2),
            orderDiscount: orderDisc.toFixed(2),
            totalDiscount: totalDiscountAmount.toFixed(2),
            nonTaxableBase: nonTaxableBase.toFixed(2),
            taxableBase: taxableBase.toFixed(2),
            tax: tax.toFixed(2),
            grandTotal: grandTotal.toFixed(2),
        };
    });

    const syncTaxAmounts = () => {
        form.items = form.items.map((item, index) => ({
            ...item,
            tax_amount: calcLineTax(item, index),
        }));
    };

    return {
        getTaxRate,
        orderLevelComputed,
        calcLineTax,
        summary,
        syncTaxAmounts,
    };
}
