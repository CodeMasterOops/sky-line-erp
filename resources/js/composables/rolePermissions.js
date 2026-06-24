/**
 * Pure helpers for the role permission picker. Kept framework-free so they can be
 * unit tested with the Node test runner and reused by the RoleForm component.
 *
 * `groups` shape: { [module: string]: { [group: string]: Array<{ permission: string, description: string }> } }
 */

/**
 * @param {Record<string, Array<{permission: string}>>} permissionGroups
 * @returns {string[]}
 */
export function flattenModule(permissionGroups) {
    const values = [];

    for (const permissions of Object.values(permissionGroups ?? {})) {
        values.push(...permissions.map((permission) => permission.permission));
    }

    return values;
}

/**
 * Every distinct permission value across all modules.
 * @param {Record<string, Record<string, Array<{permission: string}>>>} groups
 * @returns {string[]}
 */
export function flattenAll(groups) {
    const values = [];

    for (const permissionGroups of Object.values(groups ?? {})) {
        values.push(...flattenModule(permissionGroups));
    }

    return [...new Set(values)];
}

/**
 * @param {Array<{permission: string}>} permissions
 * @returns {string[]}
 */
export function groupValues(permissions) {
    return (permissions ?? []).map((permission) => permission.permission);
}

/**
 * @param {string[]} values
 * @param {string[]} selected
 * @returns {boolean}
 */
export function isAllSelected(values, selected) {
    return values.length > 0 && values.every((value) => selected.includes(value));
}

/**
 * @param {string[]} values
 * @param {string[]} selected
 * @returns {boolean}
 */
export function isSomeSelected(values, selected) {
    return values.some((value) => selected.includes(value));
}

/**
 * Returns a new selection array with `values` added or removed.
 * @param {string[]} selected
 * @param {string[]} values
 * @param {boolean} checked
 * @returns {string[]}
 */
export function toggleSelection(selected, values, checked) {
    const next = new Set(selected);

    if (checked) {
        values.forEach((value) => next.add(value));
    } else {
        values.forEach((value) => next.delete(value));
    }

    return [...next];
}

/**
 * @param {string[]} values
 * @param {string[]} selected
 * @returns {number}
 */
export function countSelected(values, selected) {
    return values.filter((value) => selected.includes(value)).length;
}

/**
 * Filters modules/groups/permissions by a search term against permission name and
 * description. Returns `[module, groups]` entries with empty groups/modules removed.
 * @param {Record<string, Record<string, Array<{permission: string, description: string}>>>} groups
 * @param {string} term
 * @returns {Array<[string, Record<string, Array<{permission: string, description: string}>>]>}
 */
export function filterModules(groups, term) {
    const entries = Object.entries(groups ?? {});
    const normalized = (term ?? "").trim().toLowerCase();

    if (!normalized) {
        return entries;
    }

    const matches = (permission) =>
        permission.description?.toLowerCase().includes(normalized) ||
        permission.permission?.toLowerCase().includes(normalized);

    const result = [];

    for (const [module, permissionGroups] of entries) {
        const filtered = {};

        for (const [group, permissions] of Object.entries(permissionGroups)) {
            const matched = permissions.filter(matches);

            if (matched.length) {
                filtered[group] = matched;
            }
        }

        if (Object.keys(filtered).length) {
            result.push([module, filtered]);
        }
    }

    return result;
}
