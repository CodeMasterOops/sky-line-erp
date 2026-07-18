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
            .sort((a, b) => String(a.code ?? '').localeCompare(String(b.code ?? '')));

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
 * Flat, depth-first options for VMultiselect with dash-indented labels so the
 * hierarchy reads through indentation (a compact en-dash per level).
 *
 * Pass `disableParents: true` for stock-location pickers: group warehouses (those
 * with sub-warehouses) render as non-selectable rows, mirroring the backend
 * WarehouseIsStockLocation rule. Leave it false for the warehouse parent picker,
 * where any warehouse may legitimately be chosen as a parent.
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, code?: string }>} warehouses
 * @param {Set<number|string>} [excludeIds]
 * @param {{ disableParents?: boolean }} [options]
 * @returns {Array<{ id: number|string, name: string, disabled?: boolean }>}
 */
export function buildWarehouseOptionsTree(warehouses, excludeIds = new Set(), { disableParents = false } = {}) {
    const options = [];

    const walk = (nodes, depth) => {
        for (const node of nodes) {
            const option = {
                id: node.warehouse.id,
                name: formatWarehouseOptionLabel(node.warehouse, depth),
            };
            if (node.children.length && disableParents) {
                option.disabled = true;
            }
            options.push(option);
            if (node.children.length) {
                walk(node.children, depth + 1);
            }
        }
    };

    walk(buildWarehouseTree(warehouses, excludeIds), 0);

    return options;
}

/**
 * @param {{ name: string, code?: string }} warehouse
 * @param {number} [depth]
 * @returns {string}
 */
export function formatWarehouseOptionLabel(warehouse, depth = 0) {
    return `${'– '.repeat(depth)}${formatWarehouseDisplayName(warehouse)}`;
}

/**
 * @param {{ name: string, code?: string }} warehouse
 * @returns {string}
 */
export function formatWarehouseDisplayName(warehouse) {
    return warehouse.code ? `${warehouse.name} (${warehouse.code})` : warehouse.name;
}
