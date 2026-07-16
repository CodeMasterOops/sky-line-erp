import {describe, it} from 'node:test';
import assert from 'node:assert';
import {
    buildWarehouseOptionsTree,
    collectDescendantIds,
    flattenWarehousesWithOutline,
    formatWarehouseOptionLabel,
} from '../warehouseTree.js';

const warehouses = [
    {id: 1, parent_id: null, name: 'Main', code: 'M'},
    {id: 2, parent_id: 1, name: 'Shelf A', code: null},
    {id: 3, parent_id: 1, name: 'Shelf B', code: 'SB'},
];

describe('warehouseTree', () => {
    it('builds nested options with children', () => {
        const tree = buildWarehouseOptionsTree(warehouses);

        assert.strictEqual(tree.length, 1);
        assert.strictEqual(tree[0].name, 'Main (M)');
        assert.strictEqual(tree[0].children.length, 2);
        assert.strictEqual(tree[0].children[0].name, 'Shelf A');
        assert.strictEqual(tree[0].children[1].name, 'Shelf B (SB)');
    });

    it('keeps parents with children selectable as a parent', () => {
        const tree = buildWarehouseOptionsTree(warehouses);

        assert.strictEqual(tree[0].disabled, undefined);
        assert.strictEqual(tree[0].children.length, 2);
    });

    it('flattens warehouses with outline and depth', () => {
        const rows = flattenWarehousesWithOutline(warehouses);

        assert.strictEqual(rows.length, 3);
        assert.strictEqual(rows[0].outline, '1');
        assert.strictEqual(rows[0].depth, 0);
        assert.strictEqual(rows[1].outline, '1.1');
        assert.strictEqual(rows[1].depth, 1);
    });

    it('collects descendant ids', () => {
        const descendants = collectDescendantIds(warehouses, 1);

        assert.ok(descendants.has(2));
        assert.ok(descendants.has(3));
        assert.strictEqual(descendants.size, 2);
    });

    it('formats option labels with indentation', () => {
        assert.strictEqual(formatWarehouseOptionLabel(warehouses[0], 0), 'Main (M)');
        assert.strictEqual(formatWarehouseOptionLabel(warehouses[1], 1), '— Shelf A');
    });
});
