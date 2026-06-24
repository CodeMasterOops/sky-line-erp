/**
 * Helpers for resolving the active state of the settings sidebar menu.
 *
 * A settings menu item points at a base route (e.g. `/admin/settings/branches`),
 * but child pages can live underneath it (e.g. `/admin/settings/branches/1/users`).
 * The sidebar must treat those child pages as active so the parent group stays
 * expanded and highlighted while navigating within the section.
 */

/**
 * Normalize a path by stripping the trailing slash, query string and hash.
 */
export function normalizeSettingsPath(path) {
    if (!path) {
        return '';
    }

    return path.split('?')[0].split('#')[0].replace(/\/$/, '');
}

/**
 * Determine whether a menu route is active for the current path.
 *
 * Returns true when the current path matches the route exactly or is nested
 * beneath it. The trailing-slash boundary prevents sibling routes that merely
 * share a prefix (e.g. `/settings/tax` vs `/settings/tax-group`) from matching.
 */
export function isSettingsRouteActive(currentPath, routePath) {
    const current = normalizeSettingsPath(currentPath);
    const route = normalizeSettingsPath(routePath);

    if (!route) {
        return false;
    }

    return current === route || current.startsWith(`${route}/`);
}
