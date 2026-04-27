/**
 * Descendant warehouse ids (excluding rootId), depth-first via parent_id links.
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null }>} flat
 * @param {number|string} rootId
 * @returns {Set<number|string>}
 */
export function collectDescendantIds(flat, rootId) {
    const byParent = new Map();
    for (const w of flat) {
        const p = w.parent_id == null || w.parent_id === '' ? null : Number(w.parent_id);
        if (!byParent.has(p)) {
            byParent.set(p, []);
        }
        byParent.get(p).push(w);
    }
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
 * Nested options for VMultiselect (supports `children` for indentation).
 *
 * @param {Array<{ id: number|string, parent_id?: number|string|null, name: string, code?: string }>} warehouses
 * @param {Set<number|string>} [excludeIds]
 * @returns {Array<{ id: number|string, name: string, children?: Array }>}
 */
export function buildWarehouseOptionsTree(warehouses, excludeIds = new Set()) {
    const exclude = new Set([...excludeIds].map((x) => Number(x)));
    const flat = (warehouses || []).filter((w) => !exclude.has(Number(w.id)));
    const byParent = new Map();
    for (const w of flat) {
        const p = w.parent_id == null || w.parent_id === '' ? null : Number(w.parent_id);
        if (!byParent.has(p)) {
            byParent.set(p, []);
        }
        byParent.get(p).push(w);
    }
    const build = (parentKey) => {
        const list = (byParent.get(parentKey) || [])
            .slice()
            .sort((a, b) => String(a.name).localeCompare(String(b.name)));
        return list.map((w) => {
            const children = build(Number(w.id));
            const node = {
                id: w.id,
                name: w.code ? `${w.name} (${w.code})` : w.name,
            };
            if (children.length) {
                node.children = children;
            }
            return node;
        });
    };
    return build(null);
}
