import {describe, it} from 'node:test';
import assert from 'node:assert';
import {
    buildCategoryOptionsTree,
    buildLeafCategoryOptions,
    collectDescendantIds,
    flattenCategoriesWithOutline,
    formatCategoryOptionLabel,
} from '../categoryTree.js';

const categories = [
    {id: 1, parent_id: null, name: 'Electronics', full_path: 'Electronics', is_leaf: false},
    {id: 2, parent_id: 1, name: 'Mobile Phones', full_path: 'Electronics > Mobile Phones', is_leaf: true},
    {id: 3, parent_id: 1, name: 'Laptops', full_path: 'Electronics > Laptops', is_leaf: true},
];

describe('categoryTree', () => {
    it('builds nested options with children', () => {
        const tree = buildCategoryOptionsTree(categories);

        assert.strictEqual(tree.length, 1);
        assert.strictEqual(tree[0].name, 'Electronics');
        assert.strictEqual(tree[0].children.length, 2);
        assert.strictEqual(tree[0].children[0].name, 'Electronics > Laptops');
        assert.strictEqual(tree[0].children[1].name, 'Electronics > Mobile Phones');
    });

    it('flattens categories with outline and depth', () => {
        const rows = flattenCategoriesWithOutline(categories);

        assert.strictEqual(rows.length, 3);
        assert.strictEqual(rows[0].outline, '1');
        assert.strictEqual(rows[0].depth, 0);
        assert.strictEqual(rows[1].outline, '1.1');
        assert.strictEqual(rows[1].depth, 1);
    });

    it('collects descendant ids', () => {
        const descendants = collectDescendantIds(categories, 1);

        assert.ok(descendants.has(2));
        assert.ok(descendants.has(3));
        assert.strictEqual(descendants.has(1), false);
    });

    it('builds leaf-only options', () => {
        const leaves = buildLeafCategoryOptions(categories);

        assert.strictEqual(leaves.length, 2);
        assert.strictEqual(leaves[0].name, 'Electronics > Laptops');
        assert.strictEqual(leaves[1].name, 'Electronics > Mobile Phones');
    });

    it('formats option label with depth', () => {
        assert.strictEqual(
            formatCategoryOptionLabel({name: 'Mobile Phones', full_path: 'Electronics > Mobile Phones'}, 1),
            '— Electronics > Mobile Phones',
        );
    });
});
