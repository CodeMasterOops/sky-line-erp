/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} categories
 * @returns {Map<number|null, Array>}
 */
function groupCategoriesByParent(categories) {
    const ids = new Set(categories.map((c) => Number(c.id)));
    const byParent = new Map();

    for (const c of categories) {
        let parentKey = null;
        if (c.parent_id != null && c.parent_id !== '') {
            const pid = Number(c.parent_id);
            if (ids.has(pid)) {
                parentKey = pid;
            }
        }
        if (!byParent.has(parentKey)) {
            byParent.set(parentKey, []);
        }
        byParent.get(parentKey).push(c);
    }

    return byParent;
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} flat
 * @param {number|string} rootId
 * @returns {Set<number|string>}
 */
export function collectDescendantIds(flat, rootId) {
    const byParent = groupCategoriesByParent(flat);
    const out = new Set();
    const queue = [Number(rootId)];
    let i = 0;
    while (i < queue.length) {
        const id = queue[i++];
        for (const c of byParent.get(id) || []) {
            const cid = Number(c.id);
            if (!out.has(cid)) {
                out.add(cid);
                queue.push(cid);
            }
        }
    }
    return out;
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, full_path?: string }>} categories
 * @param {Set<number|string>} [excludeIds]
 * @returns {Array<{ category: object, children: Array }>}
 */
export function buildCategoryTree(categories, excludeIds = new Set()) {
    const exclude = new Set([...excludeIds].map((x) => Number(x)));
    const flat = (categories || []).filter((c) => !exclude.has(Number(c.id)));
    const byParent = groupCategoriesByParent(flat);

    const build = (parentKey) => {
        const list = (byParent.get(parentKey) || [])
            .slice()
            .sort((a, b) => String(a.name).localeCompare(String(b.name)));

        return list.map((c) => ({
            category: c,
            children: build(Number(c.id)),
        }));
    };

    return build(null);
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, full_path?: string }>} categories
 * @returns {Array<object & { outline: string, depth: number }>}
 */
export function flattenCategoriesWithOutline(categories) {
    const tree = buildCategoryTree(categories);
    const rows = [];

    const walk = (nodes, pathPrefix) => {
        nodes.forEach((node, index) => {
            const path = [...pathPrefix, index + 1];
            rows.push({
                ...node.category,
                outline: path.join('.'),
                depth: path.length - 1,
            });
            if (node.children.length) {
                walk(node.children, path);
            }
        });
    };

    walk(tree, []);

    return rows;
}

/**
 * @param {{ name: string }} category
 * @returns {string}
 */
export function formatCategoryDisplayName(category) {
    return category.name;
}

/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, full_path?: string, is_leaf?: boolean }>} categories
 * @param {Set<number|string>} [excludeIds]
 * @returns {Array<{ id: number|string, name: string, children?: Array }>}
 */
export function buildCategoryOptionsTree(categories, excludeIds = new Set()) {
    const mapNode = (node) => {
        const c = node.category;
        const option = {
            id: c.id,
            name: formatCategoryDisplayName(c),
        };
        if (node.children.length) {
            option.disabled = true;
            option.children = node.children.map(mapNode);
        }
        return option;
    };

    return buildCategoryTree(categories, excludeIds).map(mapNode);
}

/**
 * Flat leaf-only options for product assignment pickers.
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, full_path?: string, is_leaf?: boolean }>} categories
 * @returns {Array<{ id: number|string, name: string }>}
 */
export function buildLeafCategoryOptions(categories) {
    const parentIds = new Set(
        (categories || [])
            .filter((c) => c.parent_id != null && c.parent_id !== '')
            .map((c) => Number(c.parent_id)),
    );

    return flattenCategoriesWithOutline(categories)
        .filter((c) => !parentIds.has(Number(c.id)))
        .map((c) => ({
            id: c.id,
            name: `${c.outline} ${c.name}`,
        }));
}

/**
 * @param {{ name: string, full_path?: string }} category
 * @param {number} [depth]
 * @returns {string}
 */
export function formatCategoryOptionLabel(category, depth = 0) {
    return `${'— '.repeat(depth)}${formatCategoryDisplayName(category)}`;
}
