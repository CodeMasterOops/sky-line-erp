import { describe, it } from 'node:test';
import assert from 'node:assert';
import {
    lineFromInvoiceItem,
    productLabelFromInvoiceLine,
    variantLabel,
} from '../creditNoteLineItem.js';

describe('creditNoteLineItem', () => {
    it('variantLabel includes sku when present', () => {
        assert.strictEqual(variantLabel({ name: 'Widget', sku: 'W-1' }), 'Widget (W-1)');
        assert.strictEqual(variantLabel({ name: 'Widget' }), 'Widget');
    });

    it('productLabelFromInvoiceLine falls back when variant missing', () => {
        assert.strictEqual(productLabelFromInvoiceLine({}), 'Unknown product');
    });

    it('lineFromInvoiceItem sets is_service for service invoice lines', () => {
        const line = lineFromInvoiceItem(
            {
                id: 10,
                product_variant_id: 5,
                product_variant: { name: 'Consulting', is_service: true },
                quantity: 2,
                rate: 500,
            },
            2,
        );

        assert.strictEqual(line.is_service, true);
        assert.strictEqual(line.invoice_item_id, 10);
        assert.strictEqual(line.product_label, 'Consulting');
    });

    it('lineFromInvoiceItem sets is_service false for stock products', () => {
        const line = lineFromInvoiceItem(
            {
                id: 11,
                product_variant_id: 6,
                product_variant: { name: 'Widget', sku: 'W-1', is_service: false },
                warehouse_id: 3,
                warehouse: { name: 'Main' },
                quantity: 1,
                rate: 100,
            },
            1,
        );

        assert.strictEqual(line.is_service, false);
        assert.strictEqual(line.warehouse_id, 3);
        assert.strictEqual(line.warehouse_name, 'Main');
    });

    it('lineFromInvoiceItem can include id for edit forms', () => {
        const line = lineFromInvoiceItem(
            {
                id: 12,
                product_variant_id: 7,
                product_variant: { name: 'Item' },
                quantity: 1,
                rate: 50,
            },
            1,
            { includeId: true },
        );

        assert.strictEqual(line.id, '');
        assert.strictEqual(line.is_service, false);
    });
});
