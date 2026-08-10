/**
 * Admin sidebar JSON shape (see resources/js/assets/json/sidebar.json):
 * - Section: { title, menu: MenuItem[] }
 * - Leaf: { menuValue, icon?, route: { name }, permission?: string, module?: string }
 *   If permission is omitted, the item is visible to all authenticated admin users.
 *   If module is omitted, the item belongs to core and is never module-gated.
 * - Submenu: { menuValue, icon?, module?: string, hasSubRoute: true, subMenus: SubMenuLeaf[] }
 * - SubMenuLeaf: { menuValue, route: { name }, permission?: string, module?: string }
 *
 * A submenu's `module` cascades to its leaves, so a whole section can be gated
 * with one key; a leaf may still name a different module (a sub-module such as
 * payroll inside HR), mirroring the inline `module:` route groups on the server.
 */

/**
 * @param {unknown[]} items
 * @param {(p: string) => boolean} check - typically (p) => hasPermission(p)
 * @param {(m: string) => boolean} [moduleCheck] - typically (m) => isModuleEnabled(m)
 */
export function filterMenuItems(items, check, moduleCheck = () => true) {
    if (!Array.isArray(items)) return [];
    return items
        .map((item) => filterMenuItem(item, check, moduleCheck))
        .filter(Boolean);
}

function filterMenuItem(item, check, moduleCheck) {
    if (item.module && !moduleCheck(item.module)) return null;

    if (item.hasSubRoute && Array.isArray(item.subMenus)) {
        const subMenus = item.subMenus
            .map((sub) => filterSubMenuLeaf(sub, check, moduleCheck, item.module))
            .filter(Boolean);
        if (subMenus.length === 0) return null;
        return { ...item, subMenus };
    }
    if (item.hasSubRouteTwo && Array.isArray(item.subMenus)) {
        const subMenus = item.subMenus
            .map((sub) => {
                if (sub.module && !moduleCheck(sub.module)) return null;
                if (sub.customSubmenuTwo && Array.isArray(sub.subMenusTwo)) {
                    const inner = sub.subMenusTwo
                        .map((s) => filterSubMenuLeaf(s, check, moduleCheck, sub.module ?? item.module))
                        .filter(Boolean);
                    if (inner.length === 0) return null;
                    return { ...sub, subMenusTwo: inner };
                }
                return filterSubMenuLeaf(sub, check, moduleCheck, item.module) ? { ...sub } : null;
            })
            .filter(Boolean);
        if (subMenus.length === 0) return null;
        return { ...item, subMenus };
    }
    if (item.permission && !check(item.permission)) return null;
    return { ...item };
}

function filterSubMenuLeaf(sub, check, moduleCheck = () => true, inheritedModule = undefined) {
    const moduleKey = sub.module ?? inheritedModule;
    if (moduleKey && !moduleCheck(moduleKey)) return null;
    if (sub.permission && !check(sub.permission)) return null;
    return { ...sub };
}

/**
 * @param {Array<{ title?: string, tittle?: string, menu: unknown[] }>} sections
 */
export function filterSidebarSections(sections, check, moduleCheck = () => true) {
    if (!Array.isArray(sections)) return [];
    return sections
        .map((section) => {
            const menu = filterMenuItems(section.menu, check, moduleCheck);
            if (menu.length === 0) return null;
            return { ...section, menu };
        })
        .filter(Boolean);
}

/**
 * Client-side search: keep sections/items whose label matches query (case-insensitive).
 */
export function filterSidebarBySearch(sections, query) {
    const q = (query || '').trim().toLowerCase();
    if (!q) return sections;

    const matchLabel = (label) => (label || '').toLowerCase().includes(q);

    return sections
        .map((section) => {
            const title = section.title ?? section.tittle ?? '';
            const menu = (section.menu || [])
                .map((item) => filterMenuItemBySearch(item, matchLabel))
                .filter(Boolean);
            if (menu.length === 0 && !matchLabel(title)) return null;
            return { ...section, menu };
        })
        .filter(Boolean);
}

/**
 * Flatten sidebar sections into a list of navigable leaf links for the
 * dashboard "Quick Links" picker. Each leaf inherits its parent submenu's
 * icon and uses the parent submenu label (or section title for top-level
 * items) as its category.
 *
 * @returns {Array<{ routeName: string, label: string, icon: string, category: string }>}
 */
export function flattenMenuLeaves(sections) {
    if (!Array.isArray(sections)) return [];

    const leaves = [];

    const pushLeaf = (leaf, icon, category) => {
        if (leaf?.route?.name) {
            leaves.push({
                routeName: leaf.route.name,
                label: leaf.menuValue,
                icon: icon || 'ti ti-link',
                category,
            });
        }
    };

    sections.forEach((section) => {
        const sectionTitle = section.title ?? section.tittle ?? '';
        (section.menu || []).forEach((item) => {
            if (item.hasSubRoute && Array.isArray(item.subMenus)) {
                item.subMenus.forEach((sub) => pushLeaf(sub, item.icon, item.menuValue));
            } else if (item.hasSubRouteTwo && Array.isArray(item.subMenus)) {
                item.subMenus.forEach((sub) => {
                    if (sub.customSubmenuTwo && Array.isArray(sub.subMenusTwo)) {
                        sub.subMenusTwo.forEach((leaf) => pushLeaf(leaf, item.icon, item.menuValue));
                    } else {
                        pushLeaf(sub, item.icon, item.menuValue);
                    }
                });
            } else {
                pushLeaf(item, item.icon, sectionTitle);
            }
        });
    });

    return leaves;
}

function filterMenuItemBySearch(item, matchLabel) {
    if (item.hasSubRoute && Array.isArray(item.subMenus)) {
        const subMenus = item.subMenus.filter((sub) =>
            matchLabel(sub.menuValue)
        );
        if (
            subMenus.length === 0 &&
            !matchLabel(item.menuValue)
        ) {
            return null;
        }
        return { ...item, subMenus };
    }
    if (item.hasSubRouteTwo && Array.isArray(item.subMenus)) {
        const subMenus = item.subMenus
            .map((sub) => {
                if (sub.customSubmenuTwo && Array.isArray(sub.subMenusTwo)) {
                    const inner = sub.subMenusTwo.filter((s) =>
                        matchLabel(s.menuValue)
                    );
                    if (
                        inner.length === 0 &&
                        !matchLabel(sub.menuValue)
                    ) {
                        return null;
                    }
                    return { ...sub, subMenusTwo: inner };
                }
                return matchLabel(sub.menuValue) ? sub : null;
            })
            .filter(Boolean);
        if (
            subMenus.length === 0 &&
            !matchLabel(item.menuValue)
        ) {
            return null;
        }
        return { ...item, subMenus };
    }
    return matchLabel(item.menuValue) ? item : null;
}
