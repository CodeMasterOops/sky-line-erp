/**
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} warehouses
 * @returns {Map<number|null, Array>}
 */
function groupWarehousesByParent(warehouses) {
    const ids = new Set(warehouses.map((w) => Number(w.id)));
    const byParent = new Map();

    for (const w of warehouses) {
        let parentKey = null;
        if (w.parent_id != null && w.parent_id !== '') {
            const pid = Number(w.parent_id);
            if (ids.has(pid)) {
                parentKey = pid;
            }
        }
        if (!byParent.has(parentKey)) {
            byParent.set(parentKey, []);
        }
        byParent.get(parentKey).push(w);
    }

    return byParent;
}

/**
 * Descendant warehouse ids (excluding rootId), depth-first via parent_id links.
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} flat
 * @param {number|string} rootId
 * @returns {Set<number|string>}
 */
export function collectDescendantIds(flat, rootId) {
    const byParent = groupWarehousesByParent(flat);
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
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, code?: string }>} warehouses
 * @param {Set<number|string>} [excludeIds]
 * @returns {Array<{ warehouse: object, children: Array }>}
 */
export function buildWarehouseTree(warehouses, excludeIds = new Set()) {
    const exclude = new Set([...excludeIds].map((x) => Number(x)));
    const flat = (warehouses || []).filter((w) => !exclude.has(Number(w.id)));
    const byParent = groupWarehousesByParent(flat);

    const build = (parentKey) => {
        const list = (byParent.get(parentKey) || [])
            .slice()
            .sort((a, b) => String(a.name).localeCompare(String(b.name)));

        return list.map((w) => ({
            warehouse: w,
            children: build(Number(w.id)),
        }));
    };

    return build(null);
}

/**
 * Depth-first flat rows with outline numbering (1, 1.1, 1.1.1).
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, code?: string }>} warehouses
 * @returns {Array<object & { outline: string, depth: number }>}
 */
export function flattenWarehousesWithOutline(warehouses) {
    const tree = buildWarehouseTree(warehouses);
    const rows = [];

    const walk = (nodes, pathPrefix) => {
        nodes.forEach((node, index) => {
            const path = [...pathPrefix, index + 1];
            rows.push({
                ...node.warehouse,
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
 * Nested options for VMultiselect (supports `children` for indentation).
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, code?: string }>} warehouses
 * @param {Set<number|string>} [excludeIds]
 * @returns {Array<{ id: number|string, name: string, children?: Array }>}
 */
export function buildWarehouseOptionsTree(warehouses, excludeIds = new Set()) {
    const mapNode = (node) => {
        const w = node.warehouse;
        const option = {
            id: w.id,
            name: formatWarehouseDisplayName(w),
        };
        if (node.children.length) {
            option.children = node.children.map(mapNode);
        }
        return option;
    };

    return buildWarehouseTree(warehouses, excludeIds).map(mapNode);
}

/**
 * @param {{ name: string, code?: string }} warehouse
 * @param {number} [depth]
 * @returns {string}
 */
export function formatWarehouseOptionLabel(warehouse, depth = 0) {
    return `${'— '.repeat(depth)}${formatWarehouseDisplayName(warehouse)}`;
}

/**
 * @param {{ name: string, code?: string }} warehouse
 * @returns {string}
 */
export function formatWarehouseDisplayName(warehouse) {
    return warehouse.code ? `${warehouse.name} (${warehouse.code})` : warehouse.name;
}
