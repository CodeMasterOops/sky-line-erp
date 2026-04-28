import {describe, it} from 'node:test';
import assert from 'node:assert';
/*
 * Manual smoke (discount UX): create draft PO/bill with % line + % order discount; save; edit and
 * confirm totals match. Repeat for invoice, sales order, quotation, credit note. Approve and reopen read-only.
 */
import {
    resolveLineDiscountMoney,
    orderDiscountMoney,
    buildOrderAllocations,
    lineItemTotalForDisplay,
} from '../purchaseOrderTotals.js';

describe('purchaseOrderTotals', () => {
    it('resolveLineDiscountMoney returns 0 for non-positive gross', () => {
        assert.strictEqual(resolveLineDiscountMoney(0, 'fixed', 10), 0);
        assert.strictEqual(resolveLineDiscountMoney(-1, 'percent', 10), 0);
    });

    it('resolveLineDiscountMoney percent caps at gross', () => {
        assert.strictEqual(resolveLineDiscountMoney(100, 'percent', 10), 10);
        assert.strictEqual(resolveLineDiscountMoney(100, 'percent', 100), 100);
        assert.strictEqual(resolveLineDiscountMoney(100, 'percent', 150), 100);
    });

    it('resolveLineDiscountMoney fixed caps at gross', () => {
        assert.strictEqual(resolveLineDiscountMoney(100, 'fixed', 200), 100);
        assert.strictEqual(resolveLineDiscountMoney(100, 'fixed', 30), 30);
    });

    it('orderDiscountMoney on zero sumLineNet is 0', () => {
        assert.strictEqual(orderDiscountMoney(0, 'fixed', 50), 0);
    });

    it('orderDiscountMoney percent and fixed', () => {
        assert.strictEqual(orderDiscountMoney(200, 'percent', 10), 20);
        assert.strictEqual(orderDiscountMoney(200, 'fixed', 50), 50);
        assert.strictEqual(orderDiscountMoney(200, 'fixed', 300), 200);
    });

    it('buildOrderAllocations is zero when sum line net is 0', () => {
        assert.deepStrictEqual(buildOrderAllocations([0, 0], 10), [0, 0]);
    });

    it('buildOrderAllocations splits pro-rata', () => {
        const a = buildOrderAllocations([60, 40], 50);
        assert.strictEqual(a[0], 30);
        assert.strictEqual(a[1], 20);
    });

    it('lineItemTotalForDisplay uses line_discount_* and optional line tax', () => {
        const items = [
            {
                quantity: '2',
                rate: '10',
                line_discount_type: 'fixed',
                line_discount_value: '5',
            },
        ];
        const t = 1.25;
        assert.strictEqual(
            lineItemTotalForDisplay(items[0], 0, items, 0, t),
            15 + t
        );
        assert.strictEqual(
            lineItemTotalForDisplay({...items[0], tax_amount: 2}, 0, items, 0),
            15 + 2
        );
    });
});
