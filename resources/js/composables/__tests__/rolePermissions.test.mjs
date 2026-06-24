import { describe, it } from 'node:test';
import assert from 'node:assert';
import {
    countSelected,
    filterModules,
    flattenAll,
    flattenModule,
    groupValues,
    isAllSelected,
    isSomeSelected,
    toggleSelection,
} from '../rolePermissions.js';

const groups = {
    Sales: {
        Invoice: [
            { permission: 'list_invoice', description: 'List invoices' },
            { permission: 'create_invoice', description: 'Create invoice' },
        ],
        Quotation: [
            { permission: 'list_quotation', description: 'List quotations' },
        ],
    },
    Inventory: {
        Product: [
            { permission: 'list_product', description: 'List products' },
            { permission: 'edit_product', description: 'Edit product' },
        ],
    },
};

describe('rolePermissions', () => {
    it('flattenModule returns every permission in a module', () => {
        assert.deepStrictEqual(flattenModule(groups.Sales), [
            'list_invoice',
            'create_invoice',
            'list_quotation',
        ]);
        assert.deepStrictEqual(flattenModule(undefined), []);
    });

    it('flattenAll returns distinct permissions across modules', () => {
        assert.deepStrictEqual(flattenAll(groups), [
            'list_invoice',
            'create_invoice',
            'list_quotation',
            'list_product',
            'edit_product',
        ]);
        assert.deepStrictEqual(flattenAll(null), []);
    });

    it('groupValues maps a group to permission strings', () => {
        assert.deepStrictEqual(groupValues(groups.Sales.Invoice), ['list_invoice', 'create_invoice']);
        assert.deepStrictEqual(groupValues(undefined), []);
    });

    it('isAllSelected is true only when every value is selected', () => {
        assert.strictEqual(isAllSelected(['a', 'b'], ['a', 'b', 'c']), true);
        assert.strictEqual(isAllSelected(['a', 'b'], ['a']), false);
        assert.strictEqual(isAllSelected([], ['a']), false, 'empty values are never "all selected"');
    });

    it('isSomeSelected is true when at least one value is selected', () => {
        assert.strictEqual(isSomeSelected(['a', 'b'], ['b']), true);
        assert.strictEqual(isSomeSelected(['a', 'b'], ['c']), false);
    });

    it('toggleSelection adds values without duplicating', () => {
        assert.deepStrictEqual(
            toggleSelection(['a'], ['a', 'b'], true),
            ['a', 'b'],
        );
    });

    it('toggleSelection removes values when unchecked', () => {
        assert.deepStrictEqual(
            toggleSelection(['a', 'b', 'c'], ['a', 'b'], false),
            ['c'],
        );
    });

    it('countSelected counts how many values are selected', () => {
        assert.strictEqual(countSelected(['a', 'b', 'c'], ['a', 'c']), 2);
        assert.strictEqual(countSelected(['a', 'b'], []), 0);
    });

    it('filterModules returns all entries when term is empty', () => {
        const result = filterModules(groups, '   ');
        assert.strictEqual(result.length, 2);
    });

    it('filterModules matches description and permission name, dropping empties', () => {
        const result = filterModules(groups, 'product');
        assert.strictEqual(result.length, 1);
        const [module, moduleGroups] = result[0];
        assert.strictEqual(module, 'Inventory');
        assert.deepStrictEqual(Object.keys(moduleGroups), ['Product']);
        assert.strictEqual(moduleGroups.Product.length, 2);
    });

    it('filterModules is case-insensitive and prunes non-matching groups', () => {
        const result = filterModules(groups, 'INVOICE');
        assert.strictEqual(result.length, 1);
        const [module, moduleGroups] = result[0];
        assert.strictEqual(module, 'Sales');
        assert.deepStrictEqual(Object.keys(moduleGroups), ['Invoice']);
    });
});
